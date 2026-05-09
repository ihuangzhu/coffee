# BOM 配方 + 生产入库（ProduceAction）设计 spec

**日期**：2026-05-09
**分支**：`feature/bom-produce`（叠在 `feature/inventory-module` 上）
**前置依赖**：库存模块（`feature/inventory-module` / PR #1）已落地的 InventoryGuard / StockTxn / StockBalance / StockOwner / StockLocation / ProductInventoryPolicy / TenantInventoryConfig
**参考文档**：
- `docs/item_stock.md` 第七节"商品 + 物料 + 配方"（行 352-425）
- `docs/superpowers/specs/2026-05-08-inventory-design.md` §4.4 BOM 区（行 323-353）

## 1. 目标与非目标

### 1.1 目标

让 `boms` / `bom_components` 两张第一期预建表从"空表"变为完整可用的能力：

1. BOM 配方 CRUD（含 component 多行明细）：支持 STANDARD（租户公共）+ STORE_CUSTOM（门店私有）两类。
2. ProduceAction（生产入库）：按指定 BOM 一次产出 N 批，原料逐项 PRODUCTION_CONSUME 出库 + 成品 PRODUCTION_OUTPUT 入库，单事务原子。
3. UI：BOM 列表/创建/编辑页 + 生产入库表单页（带消耗/产出预览）。

### 1.2 非目标（明确不做）

| 项 | 不做的原因 |
|---|---|
| 销售扣原料（MAKE_TO_ORDER） | 依赖销售模块，留待销售上线后接同一 ProduceAction 或独立 ConsumeBomAction |
| 半成品 BOM 递归展开 | 选择"两阶段独立生产"语义：半成品需先单独 Produce 入库，再 Produce 成品 |
| 生产工单单据头（`production_orders`） | 第一期不引入工单状态机；ProduceAction 直接一次性写入流水 |
| 整批反向 ReverseProduceAction | 用户如需撤销可用既有 ReverseStockTxnAction 逐条撤；批量撤销留待后续 |
| 多门店中央厨房（跨 owner 生产） | 第一期单 store 单 owner 单 location；生产时 store_id 隐式绑定 owner |
| 批次号 / 保质期联动（lot_no / expiry） | 库存模块本身未启用 stock_quants 写入；BOM 阶段一并不引入 |
| 成本核算（component 成本上卷） | 留待财务模块 |
| BOM 版本管理 | 编辑直接覆盖 components；不做 v1/v2 历史回溯 |

## 2. 关键业务约束

以下约束要在控制器 / Action / 测试中显式验证：

1. **BOM 唯一性**（应用层校验）：
   - 同 `(tenant_id, output_sku_id, bom_type='STANDARD', deleted_at=NULL, status='active')` 至多 1 条。
   - 同 `(tenant_id, output_sku_id, bom_type='STORE_CUSTOM', store_id=X, deleted_at=NULL, status='active')` 至多 1 条。
   - 不强制只能 1 个 active：允许同 sku 同时有 STANDARD + 1 个 STORE_CUSTOM 共存（覆盖语义路由器留给后续阶段，本期 ProduceAction 通过显式 `bom_id` 触发，不做"按 sku 自动选 BOM"）。
   - 不依赖 DB 唯一索引：SQLite/MySQL 对 NULL distinctness 行为一致（NULL 视为不同），靠应用层守。

2. **类型校验**（控制器 Validator after rules）：
   - `output_sku.item.item_type ∈ {SALE_PRODUCT, FINISHED_GOOD, SEMI_FINISHED}`
   - 每个 `component_sku.item.item_type ∈ {RAW_MATERIAL, SEMI_FINISHED, PACKAGE}`
   - 类型不符 → 422 ValidationException

3. **同租户校验**：output_sku 与所有 component_sku 的 `tenant_id` 必须等于 BOM 的 tenant_id（控制器层 Rule::exists with where）。

4. **bom_type 与 store_id 互锁**：
   - `bom_type=STANDARD` ⇒ `store_id` 必须 NULL（`Rule::prohibitedIf`）
   - `bom_type=STORE_CUSTOM` ⇒ `store_id` 必填且 store 属于当前 tenant（`Rule::requiredIf` + `Rule::exists`）

5. **环检测**：每行 `component_sku_id ≠ output_sku_id`（不允许 BOM 直接引用自身；间接环本期不检测，靠"不递归"语义自然规避）。

6. **生产时不递归**：ProduceAction 只扣直接 component；半成品库存不足按 negative_stock 规则处理（默认拒绝）。

7. **Toggle gate 全程生效**：ProduceAction 对 output_sku **以及每一个** component_sku 都要过 `InventoryGuard::assertEnabled`（5 层 toggle），任一失败 → `InventoryDisabledException`。

8. **STORE_CUSTOM 隔离**：ProduceAction 入参 `store_id` 必须等于 `bom.store_id`（当 bom_type=STORE_CUSTOM）；不一致 → 403 `BOM_STORE_MISMATCH`。

## 3. Schema（已存在，本期不改表）

`boms` 字段（来自 `2026_05_08_000071_create_boms_table.php`）：

```
id              CHAR(26)   PK ULID
tenant_id       CHAR(26)
output_sku_id   CHAR(26)   产出 SKU
output_qty      DECIMAL(18,4)
bom_type        ENUM('STANDARD','STORE_CUSTOM')
store_id        CHAR(26) NULL  仅 STORE_CUSTOM 时填
status          ENUM('active','disabled')  默认 'active'
created_at / updated_at / deleted_at（软删）
```

`bom_components`（来自 `2026_05_08_000072_create_bom_components_table.php`）：

```
id                 CHAR(26)
bom_id             CHAR(26)   FK boms.id（CASCADE）
component_sku_id   CHAR(26)
consume_qty        DECIMAL(18,4)
loss_rate          DECIMAL(6,4)  默认 0.0000（10% 写 0.1000）
sequence_no        SMALLINT      默认 0
created_at / updated_at
```

**本期不增加任何字段、不改任何索引。**

## 4. BOM CRUD

### 4.1 控制器 `TenantBomController`

| 方法 | 路由 | 渲染 / 行为 |
|---|---|---|
| `index` | `GET /tenant/boms` | Inertia 渲染 `Bom/Index.vue`，分页列表 |
| `create` | `GET /tenant/boms/create` | 渲染 `Bom/Create.vue`，props 带 output 候选 SKU + component 候选 SKU + stores |
| `store` | `POST /tenant/boms` | 校验 + 事务创建 BOM + components → 跳列表 + flash success |
| `edit` | `GET /tenant/boms/{bom}/edit` | 渲染 `Bom/Edit.vue`，bom 必须属于当前 tenant |
| `update` | `PATCH /tenant/boms/{bom}` | 事务：删除原 components + 重建 + 更新 BOM 主体 |
| `destroy` | `DELETE /tenant/boms/{bom}` | 软删 |

### 4.2 Validator 规则

```php
[
    'output_sku_id' => ['required', Rule::exists('item_skus', 'id')->where('tenant_id', $tid)],
    'output_qty'    => ['required', 'numeric', 'gt:0'],
    'bom_type'      => ['required', Rule::in(['STANDARD', 'STORE_CUSTOM'])],
    'store_id'      => [
        Rule::requiredIf(fn () => request('bom_type') === 'STORE_CUSTOM'),
        Rule::prohibitedIf(fn () => request('bom_type') === 'STANDARD'),
        Rule::exists('stores', 'id')->where('tenant_id', $tid),
    ],
    'status'        => ['required', Rule::in(['active', 'disabled'])],
    'components'                    => ['array', 'min:1'],
    'components.*.component_sku_id' => ['required', Rule::exists('item_skus', 'id')->where('tenant_id', $tid)],
    'components.*.consume_qty'      => ['required', 'numeric', 'gt:0'],
    'components.*.loss_rate'        => ['required', 'numeric', 'gte:0', 'lte:1'],
    'components.*.sequence_no'      => ['required', 'integer', 'gte:0'],
]
```

After-validate 阶段（`withValidator` 或单独抛 `ValidationException::withMessages`）：

- output_sku 的 item_type 在合法集合里；否则 errors `output_sku_id`
- 每行 component_sku 的 item_type 在合法集合里；否则 errors `components.{i}.component_sku_id`
- 每行 component_sku_id ≠ output_sku_id；否则 errors `components.{i}.component_sku_id`
- 唯一性查询：`Bom::where(... output_sku_id, bom_type, store_id, status='active', deleted_at NULL)` 排除自身（update 时）有结果 → errors `output_sku_id`

### 4.3 Vue 页

**`Bom/Index.vue`**：
- 顶部 Tab：`STANDARD` / `STORE_CUSTOM`（切换查询参数 `bom_type`）
- ElTable 列：`output_sku_name`（item.name + sku spec）/ `output_qty` / `bom_type` / `store_name`（STORE_CUSTOM 时）/ component_count / status / 操作（编辑/删除）
- 顶部右侧"新建配方"按钮 → `tenant.boms.create`

**`Bom/Create.vue` / `Edit.vue`**（共用结构）：
- 顶部 ElForm：
  - output_sku：远程搜索下拉（候选 = item_type ∈ {SALE_PRODUCT, FINISHED_GOOD, SEMI_FINISHED}）
  - output_qty：ElInputNumber，min=0.0001
  - bom_type：ElRadioGroup（STANDARD/STORE_CUSTOM）
  - store_id：ElSelect（仅 bom_type=STORE_CUSTOM 时显示）
  - status：ElSelect（active/disabled）
- 下方 component 子表（v-for + ElRow）：
  - component_sku：远程搜索下拉（候选 = item_type ∈ {RAW_MATERIAL, SEMI_FINISHED, PACKAGE}）
  - consume_qty：ElInputNumber
  - loss_rate：ElInputNumber，0~1，step=0.01，UI 用百分比展示但提交 0~1 数字
  - sequence_no：ElInputNumber
  - 删除行按钮
- 底部"+添加组件"按钮（form.components.push 新行）
- 提交：`router.post('/tenant/boms', form as unknown as Record<string, FormDataConvertible>)`

### 4.4 Inertia props 优化

`create` / `edit` Inertia render 时一次性下发候选 SKU 列表：

```php
return Inertia::render('Bom/Create', [
    'outputSkus'    => ItemSku::with('item')->whereHas('item', fn ($q) =>
                          $q->whereIn('item_type', ['SALE_PRODUCT','FINISHED_GOOD','SEMI_FINISHED']))
                       ->where('tenant_id', $tid)->get(['id','sku_code','spec_name','item_id']),
    'componentSkus' => ItemSku::with('item')->whereHas('item', fn ($q) =>
                          $q->whereIn('item_type', ['RAW_MATERIAL','SEMI_FINISHED','PACKAGE']))
                       ->where('tenant_id', $tid)->get(['id','sku_code','spec_name','item_id']),
    'stores'        => Store::where('tenant_id', $tid)->get(['id','name']),
]);
```

数据量小（百量级）时直接全量；后续 SKU 数量大可换搜索 API。

## 5. ProduceAction（生产入库）

### 5.1 签名

```php
namespace App\Modules\Inventory\Actions;

class ProduceAction
{
    public function handle(
        string $tenantId,
        string $storeId,
        string $bomId,
        string $batchQty,            // 批次份数（按 BOM output_qty 倍数），bcmath string
        string $operatorId,          // user.id（必填，stock_txns.operator_id 非空）
        ?string $sourceLocationId = null,   // 默认 store DEFAULT location
        ?string $outputLocationId = null,   // 默认 store DEFAULT location
        array $meta = [],            // 写入两类 stock_txns 的 meta_json
    ): array;                        // ['consume_txn_ids' => [...], 'output_txn_id' => '...']
}
```

`batchQty` 校验：必须是 `numeric` 且 `> 0`，bcmath 4 位精度。

### 5.2 数学

- 实际产出量 = `bcmul(bom.output_qty, batchQty, 4)`
- 每 component 实际消耗 = `bcmul( bcmul(consume_qty, bcadd('1', loss_rate, 4), 4), batchQty, 4 )`

例：
- BOM `output_qty=1`、component `consume_qty=10, loss_rate=0.1`、batchQty=`5`
- output 实际产出 = 5
- component 实际消耗 = 10 × 1.1 × 5 = 55

### 5.3 事务流程

```
DB::transaction(function () use (...) {
  // 0. 加载 BOM（with components.componentSku.item）
  $bom = Bom::with('components.componentSku.item')
            ->where('tenant_id', $tenantId)->where('id', $bomId)
            ->where('status', 'active')->whereNull('deleted_at')
            ->first();
  if (!$bom) throw new BusinessException('BOM_NOT_FOUND', '配方不存在或已停用', 404);
  if ($bom->bom_type === BomType::StoreCustom && $bom->store_id !== $storeId)
      throw new BusinessException('BOM_STORE_MISMATCH', '门店与配方不匹配', 403);
  if ($bom->components->isEmpty())
      throw new BusinessException('BOM_NO_COMPONENTS', '配方无组件', 422);

  // 1. Toggle gate：output 必过；每个 component 也必过
  InventoryGuard::assertEnabled($tenantId, $storeId, $bom->output_sku_id);
  foreach ($bom->components as $c) {
      InventoryGuard::assertEnabled($tenantId, $storeId, $c->component_sku_id);
  }

  // 2. 解析 owner + 默认 location
  $owner = StockOwner::where('tenant_id', $tenantId)
              ->where('owner_type', 'STORE')->where('owner_ref_id', $storeId)
              ->firstOrFail();
  $defaultLoc = StockLocation::where('stock_owner_id', $owner->id)
              ->where('location_code', 'DEFAULT')->firstOrFail();
  $sourceLocId = $sourceLocationId ?? $defaultLoc->id;
  $outputLocId = $outputLocationId ?? $defaultLoc->id;

  // 3. 计算消耗计划
  $totalOutputQty = bcmul($bom->output_qty, $batchQty, 4);
  $consumePlans = [];
  foreach ($bom->components as $c) {
      $consumePlans[] = [
          'sku_id' => $c->component_sku_id,
          'qty' => bcmul(
              bcmul($c->consume_qty, bcadd('1', $c->loss_rate, 4), 4),
              $batchQty, 4
          ),
      ];
  }

  // 4. 锁顺序：sku_id 字典序，避免死锁
  usort($consumePlans, fn ($a, $b) => strcmp($a['sku_id'], $b['sku_id']));

  // 5. 锁所有 component balance（不存在视为 0；产出 balance 也锁/创建）
  $balances = [];
  foreach ($consumePlans as $plan) {
      $balances[$plan['sku_id']] = StockBalance::query()
          ->where('tenant_id', $tenantId)->where('stock_owner_id', $owner->id)
          ->where('location_id', $sourceLocId)->where('sku_id', $plan['sku_id'])
          ->lockForUpdate()->first();
  }
  $outputBalance = StockBalance::query()
      ->where('tenant_id', $tenantId)->where('stock_owner_id', $owner->id)
      ->where('location_id', $outputLocId)->where('sku_id', $bom->output_sku_id)
      ->lockForUpdate()->first()
      ?? StockBalance::create([
          'id' => Str::ulid(), 'tenant_id' => $tenantId, 'stock_owner_id' => $owner->id,
          'location_id' => $outputLocId, 'sku_id' => $bom->output_sku_id,
          'available_qty' => '0', 'reserved_qty' => '0',
          'in_transit_qty' => '0', 'damaged_qty' => '0', 'version' => 0,
      ]);

  // 6. 校验余额（按 negative_stock 双层 AND）
  $tenantCfg = TenantInventoryConfig::find($tenantId);
  foreach ($consumePlans as $plan) {
      $available = $balances[$plan['sku_id']]?->available_qty ?? '0';
      $remaining = bcsub($available, $plan['qty'], 4);
      if (bccomp($remaining, '0', 4) < 0) {
          $policy = ProductInventoryPolicy::find($plan['sku_id']);
          if (!($tenantCfg->negative_stock_allowed && $policy?->allow_negative_stock)) {
              throw new BusinessException('INSUFFICIENT_STOCK',
                  "原料 {$plan['sku_id']} 库存不足", 422);
          }
      }
  }

  // 7. 写入 N 条 PRODUCTION_CONSUME
  $consumeTxnIds = [];
  $occurredAt = now();
  foreach ($consumePlans as $plan) {
      $txn = StockTxn::create([
          'tenant_id' => $tenantId, 'stock_owner_id' => $owner->id,
          'location_id' => $sourceLocId, 'sku_id' => $plan['sku_id'],
          'qty_change' => bcsub('0', $plan['qty'], 4),  // 负数
          'direction' => 'OUT',
          'biz_type' => 'PRODUCTION_CONSUME',
          'biz_order_type' => 'BOM',
          'biz_order_id' => $bomId,
          'occurred_at' => $occurredAt,
          'meta_json' => array_merge($meta, [
              'bom_id' => $bomId, 'batch_qty' => $batchQty,
              'output_sku_id' => $bom->output_sku_id,
          ]),
          'operator_id' => $operatorId,
      ]);
      $consumeTxnIds[] = $txn->id;
      // 更新 balance
      if ($balances[$plan['sku_id']]) {
          DB::table('stock_balances')->where('id', $balances[$plan['sku_id']]->id)
              ->update([
                  'available_qty' => bcsub($balances[$plan['sku_id']]->available_qty, $plan['qty'], 4),
                  'version' => $balances[$plan['sku_id']]->version + 1,
              ]);
      } else {
          // 不存在又允许负库存：创建一行 = -qty
          StockBalance::create([
              'id' => Str::ulid(), 'tenant_id' => $tenantId, 'stock_owner_id' => $owner->id,
              'location_id' => $sourceLocId, 'sku_id' => $plan['sku_id'],
              'available_qty' => bcsub('0', $plan['qty'], 4), 'reserved_qty' => '0',
              'in_transit_qty' => '0', 'damaged_qty' => '0', 'version' => 1,
          ]);
      }
  }

  // 8. 写入 PRODUCTION_OUTPUT
  $outputTxn = StockTxn::create([
      'tenant_id' => $tenantId, 'stock_owner_id' => $owner->id,
      'location_id' => $outputLocId, 'sku_id' => $bom->output_sku_id,
      'qty_change' => $totalOutputQty,
      'direction' => 'IN',
      'biz_type' => 'PRODUCTION_OUTPUT',
      'biz_order_type' => 'BOM',
      'biz_order_id' => $bomId,
      'occurred_at' => $occurredAt,
      'meta_json' => array_merge($meta, [
          'bom_id' => $bomId, 'batch_qty' => $batchQty,
          'consume_txn_ids' => $consumeTxnIds,
      ]),
      'operator_id' => $operatorId,
  ]);
  DB::table('stock_balances')->where('id', $outputBalance->id)
      ->update([
          'available_qty' => bcadd($outputBalance->available_qty, $totalOutputQty, 4),
          'version' => $outputBalance->version + 1,
      ]);

  return ['consume_txn_ids' => $consumeTxnIds, 'output_txn_id' => $outputTxn->id];
});

// 9. 提交后逐 sku 发事件（StockChanged 签名见 Events/StockChanged.php）
event(new StockChanged(
    $tenantId, $owner->id, $outputLocId, $bom->output_sku_id,
    $outputTxn->id, 'PRODUCTION_OUTPUT'
));
foreach (array_combine(array_column($consumePlans, 'sku_id'), $consumeTxnIds) as $skuId => $txnId) {
    event(new StockChanged(
        $tenantId, $owner->id, $sourceLocId, $skuId,
        $txnId, 'PRODUCTION_CONSUME'
    ));
}
```

### 5.4 异常分类

| 错误码 | HTTP | 触发场景 |
|---|---|---|
| `BOM_NOT_FOUND` | 404 | bom 不存在 / 跨租户 / 已软删 / status≠active |
| `BOM_STORE_MISMATCH` | 403 | STORE_CUSTOM bom 的 store_id ≠ 入参 storeId |
| `BOM_NO_COMPONENTS` | 422 | bom 无 component（防御性） |
| `INVENTORY_DISABLED` | 422 | InventoryGuard 任一层失败（output 或任意 component） |
| `INSUFFICIENT_STOCK` | 422 | 原料不足且 negative_stock 未放行 |

`InsufficientStockException` 复用 `BusinessException` 3-arg 形式（`code, message, httpStatus`）。

## 6. ProduceAction Web 入口

### 6.1 控制器 `TenantProduceController`

| 方法 | 路由 | 行为 |
|---|---|---|
| `create` | `GET /tenant/produce` | 渲染 `Produce/Index.vue`，props 带 active BOM 列表 + stores |
| `store` | `POST /tenant/produce` | 校验入参 → 调 ProduceAction → flash 跳回 |
| `preview` | `GET /tenant/produce/preview?bom_id=&batch_qty=&store_id=` | JSON：返回 `{ output: {sku_id, name, qty}, consumes: [{sku_id, name, qty, available, sufficient}] }` |

### 6.2 Vue 页 `Produce/Index.vue`

- 顶部 ElForm：
  - store_id：ElSelect
  - bom_id：远程搜索下拉（按 store_id + bom_type 过滤：STANDARD 全部 + 当前 store 的 STORE_CUSTOM）
  - batch_qty：ElInputNumber，min=0.0001
  - source_location_id / output_location_id：可选，默认隐藏（"高级"折叠）
- 中段：选 bom 和 batch_qty 后调 `/tenant/produce/preview` 实时刷新预览：
  - 产出：`output_sku_name × N`
  - 消耗：表格列出每行 component（含 loss 后实际量 + 当前库存 + 状态：充足/不足）
- 底部"提交生产"按钮（任一 component 不足且 negative_stock 未放行时禁用）
- 提交：`router.post('/tenant/produce', form as unknown as Record<string, FormDataConvertible>)`

## 7. 路由

`routes/web.php` 在 `tenant.` 前缀组下追加：

```php
use App\Modules\Inventory\Http\Controllers\Web\TenantBomController;
use App\Modules\Inventory\Http\Controllers\Web\TenantProduceController;

Route::middleware(['auth', 'tenant.bind'])->prefix('tenant')->name('tenant.')->group(function () {
    Route::resource('boms', TenantBomController::class)->except(['show']);
    Route::get('produce', [TenantProduceController::class, 'create'])->name('produce.create');
    Route::post('produce', [TenantProduceController::class, 'store'])->name('produce.store');
    Route::get('produce/preview', [TenantProduceController::class, 'preview'])->name('produce.preview');
});
```

实际套用 `routes/web.php` 中已有的 tenant 分组结构。

## 8. 权限

`Permission` 枚举追加 6 cases：

```php
case BomsRead          = 'boms.read';
case BomsCreate        = 'boms.create';
case BomsUpdate        = 'boms.update';
case BomsDelete        = 'boms.delete';
case ProductionExecute = 'production.execute';
case ProductionRead    = 'production.read';
```

新迁移 `seed_bom_produce_permissions`（在 `2026_05_08_000073_seed_inventory_permissions.php` 之后）：

| 角色 | 新增权限 |
|---|---|
| `RoleOwner` | 6 项全部 |
| `RoleAdmin` | 6 项全部 |
| `RoleManager` | `boms.read` + `production.read` + `production.execute`（不能改/删 BOM 主数据） |

控制器各方法用 `Gate::authorize` 或路由中间件 `permission:` 守。

## 9. 侧栏 + i18n

`resources/js/composables/useNavigation.ts` inventory 模块下追加（位置：stock 入口之前）：

```ts
{ name: 'boms',    label: t('nav.inventory.boms'),    route: 'tenant.boms.index',    permission: 'boms.read' },
{ name: 'produce', label: t('nav.inventory.produce'), route: 'tenant.produce.create', permission: 'production.execute' },
```

`resources/js/lib/locales/zh-CN/nav.ts` 追加 `inventory.boms = '配方管理'` / `inventory.produce = '生产入库'`。

## 10. 测试矩阵

### 10.1 Schema 测试

| 文件 | 用例 |
|---|---|
| `BomSchemaTest.php` | 表存在 / 字段类型 / 软删 / FK / cast / `outputSku()` 关系 / 跨租户 BelongsToTenant 全局 scope 生效 |
| `BomComponentSchemaTest.php` | 表存在 / 字段类型 / FK CASCADE / `bom()` `componentSku()` 关系 |

### 10.2 Web 测试 `TenantBomWebTest.php`

- 列表展示 + 跨租户隔离（其他 tenant 的 BOM 不出现）
- 创建：填合法 → 重定向 + flash + DB 存在
- 创建：output_sku item_type=RAW_MATERIAL → 422
- 创建：component_sku item_type=SALE_PRODUCT → 422
- 创建：component_sku_id == output_sku_id → 422
- 创建：bom_type=STANDARD 但 store_id 非空 → 422
- 创建：bom_type=STORE_CUSTOM 但 store_id 空 → 422
- 创建：同 (sku, type, store, status=active) 重复 → 422
- 创建：跨租户 output_sku → 422
- 编辑：覆盖 components（先删后建）
- 删除：软删后从列表消失
- 跨租户：访问他人 BOM 编辑/删除 → 404

### 10.3 Action 测试 `ProduceActionTest.php`

- 正常：BOM 1 component（10g, loss 0.1）batch=5 → consume_qty 写入 -55、output 写入 +5、balance 准确
- batch_qty=2 时各项 ×2
- output_qty=0.5 BOM × batch=4 = 2 杯 output（小数 BOM）
- balance 不存在的 component（=0） + 允许负库存 → 创建一行负 balance
- balance=0 + 不允许负库存 → 抛 INSUFFICIENT_STOCK 且无任何写入（事务回滚）
- 跨租户 BOM → 抛 BOM_NOT_FOUND（404）
- STORE_CUSTOM bom 与传入 store_id 不一致 → 抛 BOM_STORE_MISMATCH（403）
- output_sku toggle 关闭（policy.track_type=NONE） → 抛 InventoryDisabledException
- component sku toggle 关闭 → 抛 InventoryDisabledException
- bom 无 component → 抛 BOM_NO_COMPONENTS
- bom status=disabled → 抛 BOM_NOT_FOUND
- bom 软删 → 抛 BOM_NOT_FOUND
- source_location_id 显式传入与 output_location_id 不同 → 两个 txn 落对应 location
- 多 component 锁顺序：mock 同 sku_id 顺序传入，实际 lock 按字典序
- 提交后 StockChanged 事件触发次数 = N+1（每 sku 一次）

### 10.4 ProduceController Web 测试 `TenantProduceWebTest.php`

- create 表单展示 + props 带 boms / stores
- store 提交合法 → 跳转 + flash + DB 流水写入
- preview JSON：返回 output + consumes 明细，每行带 sufficient bool
- preview：跨租户 bom_id → 404
- store：跨租户 bom_id → 404
- 跨租户 store_id → 422（store 不存在于当前 tenant）

### 10.5 权限测试 `BomProducePermissionTest.php`

- 6 个新权限点都被 Owner / Admin 包含
- Manager 仅有 `boms.read` + `production.read` + `production.execute`，不含 boms.create/update/delete
- 缺权限访问对应路由 → 403

预期总用例：~50 个新增；目标 290 → ~340 全绿。

## 11. 任务边界（subagent-driven-development 拆分）

| 任务 | 输出 | 工具 / 文件 |
|---|---|---|
| **T1** | Permission 枚举扩 6 + seed migration + PermissionSeedTest 新增断言 | `Permission.php`, `2026_05_09_xxxxxx_seed_bom_produce_permissions.php`, `PermissionSeedTest.php`, regress `SystemRolesSeedTest.php` `MultiBindingMergeTest.php` |
| **T2** | TenantBomController + Validator + Bom/Index/Create/Edit Vue + schema 测试 + Web 测试 | `TenantBomController.php`, 3 Vue, 4 Tests, `routes/web.php` |
| **T3** | ProduceAction（含 loss / lockForUpdate / N+1 txns / event） + ProduceActionTest | `ProduceAction.php`, `ProduceActionTest.php` |
| **T4** | TenantProduceController + Produce/Index Vue（含预览） + Web 测试 | `TenantProduceController.php`, `Produce/Index.vue`, `TenantProduceWebTest.php` |
| **T5** | 侧栏 + i18n + 路由收尾 | `useNavigation.ts`, `nav.ts` |
| **T6** | 最终 review fix（如果有） | — |

每任务走 implementer → spec reviewer → code quality reviewer 双轮 review。

## 12. 已知风险与权衡

| 风险 | 缓解 |
|---|---|
| BOM 唯一性靠应用层校验，并发创建可能漏过 | BOM 创建低频；如必要后续加 generated column + unique index |
| ProduceAction 锁多 SKU 行可能死锁（不同进程不同 BOM 重叠 component） | 锁顺序按 sku_id 字典序，全局一致 |
| 半成品两阶段生产用户体验冗余（先 ProduceAction 半成品，再 ProduceAction 成品） | 文档/UI 提示；后续阶段加 EXPLODE_IF_LOW 策略支持 |
| MAKE_TO_ORDER 没销售模块对接 | 销售模块上线时复用同一 ProduceAction（meta.biz_type=SALE_TRIGGER） |
| Reverse 整批撤销缺失 | 用户用 ReverseStockTxnAction 逐条撤；后续做批量 |
| BOM 编辑直接覆盖 components 无版本 | 当前不需要历史回溯；后续如需可加 bom_versions 表 |

---

**Spec 版本**：v1（2026-05-09）
**前置依赖**：feature/inventory-module（PR #1）
**后续阶段**：销售扣原料（依赖销售模块）/ 整批 Reverse / BOM 嵌套展开 / 生产工单
