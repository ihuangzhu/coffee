# BOM CRUD + ProduceAction Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 把已建好的 `boms` / `bom_components` 两张空表落成完整能力——BOM 配方 CRUD + ProduceAction 生产入库（按 BOM 一次产出 N 批，原料 PRODUCTION_CONSUME 出库 + 成品 PRODUCTION_OUTPUT 入库，单事务原子）。

**Architecture:** 复用 `feature/inventory-module` 已有 InventoryGuard / StockTxn / StockBalance / StockOwner 设施；ProduceAction 写 N+1 条 stock_txns，按字典序锁 stock_balance 防死锁，bcmath 4 位精度，事务后逐 sku emit StockChanged 事件。控制器走 Inertia + Vue + Element Plus（沿用 Stock/Item 模块惯例）。

**Tech Stack:** Laravel 13 / PHP 8.3 / SQLite (test) + MySQL 8.x / Pest 3 / Vue 3 + Inertia.js v2 + Element Plus + TypeScript

**Spec:** `docs/superpowers/specs/2026-05-09-bom-produce-design.md`
**Branch:** `feature/bom-produce`（叠在 `feature/inventory-module` 之上）

---

## 重要执行约定（每个 implementer subagent 都要先读）

### 测试运行
- **NEVER** 用 `composer test`（会失败：`Target class [config] does not exist`）
- 用 `./vendor/bin/pest`（serial）跑全量；用 `./vendor/bin/pest --filter='xxx'` 跑单测
- 不要尝试 `--parallel`（paratest 配置问题，会挂起）

### 项目惯例
- 模块表名以原文档为基线，**禁止去前缀/缩写**（`item_skus` 不要 → `skus`）。Laravel 复数化（`item_sku → item_skus`）OK
- 主键 ULID（CHAR(26)），`HasUlid` trait 自动生成；除 `stock_txns` 是 BIGINT 自增（append-only ledger）
- `BelongsToTenant` trait + `CurrentTenant` 提供 tenant 全局 scope；当显式 `where('tenant_id', ...)` 查询时用 `withoutGlobalScopes()` 避免双层过滤
- bcmath：所有 qty / decimal 计算用字符串入参 + 4 位精度（`bcadd($a, $b, 4)` / `bcsub` / `bcmul` / `bccomp`）
- 路由风格：flat 声明在 `Route::prefix('tenant')->middleware('auth')->group(...)` 内部；POST 用 `storeFromForm` 命名（参考 categories / items）；不用 `Route::resource()`
- 控制器复用：`requireCurrentTenant(Request)` 私有方法返回 tenant_id（参见 `TenantStockController.php:162`）
- Vue 路径：`resources/js/Pages/tenant/Bom/{Index,Create,Edit}.vue` 和 `tenant/Produce/Index.vue`（注意 WSL 文件系统不区分大小写但 git 跟踪是 `Pages/`）
- `defineProps<...>()` 不要用 `const props =` 包裹（vue-tsc 报 unused prop）
- `router.post(url, form as unknown as Record<string, FormDataConvertible>)` —— 从 `@inertiajs/core` import `FormDataConvertible`
- ElTag `:type` 用 `undefined` 不要用 `''`

### 一致字段名（与 schema 对齐，不要写错）
- `stock_balances`: `id, tenant_id, stock_owner_id, location_id, sku_id, available_qty, reserved_qty, in_transit_qty, damaged_qty, version`（**不是** `owner_id` 不是 `on_hand_qty`）
- `stock_locations`: `id, tenant_id, stock_owner_id, location_code, location_name, location_type, status`（**不是** `code` 是 `location_code`）
- `stock_txns`: `id (bigIncrements), tenant_id, biz_type, biz_order_type, biz_order_id, stock_owner_id, location_id, sku_id, qty_change, unit_cost_cents, amount_cents, direction, occurred_at, operator_id, meta_json, created_at`（**operator_id 必填**，没有 deleted_at）
- `boms`: `id, tenant_id, output_sku_id, output_qty, bom_type, store_id, status, created_at, updated_at, deleted_at`
- `bom_components`: `id, bom_id, component_sku_id, consume_qty, loss_rate, sequence_no, created_at, updated_at`
- `Permission` 实际角色 code：`TenantAdmin` / `StoreManager` / `StoreClerk`（**不是** Owner/Admin/Manager）

### Action 写法
- `static handle(...)`（参考 `AdjustStockAction.php`）
- `StockChanged::dispatch(tenantId, stockOwnerId, locationId, skuId, txnId, bizType)`
- `StockBalance::query()->withoutGlobalScopes()->...->lockForUpdate()->first()`
- `BusinessException` 是 3-arg：`new BusinessException($code, $message, $httpStatus)`

---

## Task 概览

| Task | 输出 | Reviewer focus |
|---|---|---|
| **T1** | Permission 枚举扩 6 + seed migration + PermissionSeedTest 扩展 + 回归现有 SystemRolesSeedTest / MultiBindingMergeTest | spec §8 权限对齐；3 角色权限矩阵正确 |
| **T2** | TenantBomController + Validator + 3 Vue 页 + Schema 测试 + Web 测试 | spec §2/§4 业务约束 11 条全部覆盖 |
| **T3** | ProduceAction 单文件 + ProduceActionTest 大批用例 | spec §5 算法 + §10.3 测试矩阵全部覆盖 |
| **T4** | TenantProduceController + Produce/Index Vue + preview JSON + Web 测试 | spec §6 三个端点 + 跨租户隔离 |
| **T5** | 侧栏 + i18n + 路由收尾 + 端到端冒烟测试 | spec §7/§9 路由完备性 |

---

## Task 1: Permission 扩展 + Seed Migration

**Files:**
- Modify: `app/Modules/Authorization/Enums/Permission.php`（追加 6 cases）
- Create: `database/migrations/2026_05_09_000001_seed_bom_produce_permissions.php`
- Modify: `app/Modules/Inventory/Tests/Feature/PermissionSeedTest.php`（扩展期望数组）
- Modify（回归修复）: 任何因 Permission 数量变化而失败的现有测试

- [ ] **Step 1.1: 扩展 Permission 枚举**

把 6 个 case 追加到 `app/Modules/Authorization/Enums/Permission.php` 文件末尾（在 `InventoryPolicyUpdate` case 之后，闭合 `}` 之前）：

```php
    // ── BOM / 生产入库（2026-05-09 BOM Action 阶段）
    case BomsRead = 'boms.read';
    case BomsCreate = 'boms.create';
    case BomsUpdate = 'boms.update';
    case BomsDelete = 'boms.delete';
    case ProductionExecute = 'production.execute';
    case ProductionRead = 'production.read';
```

- [ ] **Step 1.2: 写新种子迁移**

创建 `database/migrations/2026_05_09_000001_seed_bom_produce_permissions.php`，沿用 `2026_05_08_000073_seed_inventory_permissions.php` 同款 array_merge 模式：

```php
<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** @var array<string, list<string>> */
    private array $rolePerms = [];

    public function __construct()
    {
        $this->rolePerms = [
            'TenantAdmin' => [
                'boms.read', 'boms.create', 'boms.update', 'boms.delete',
                'production.execute', 'production.read',
            ],
            'StoreManager' => [
                'boms.read', 'boms.create', 'boms.update',
                'production.execute', 'production.read',
                // 不允许 boms.delete
            ],
            'StoreClerk' => [
                'boms.read', 'production.read', 'production.execute',
            ],
        ];
    }

    public function up(): void
    {
        foreach ($this->rolePerms as $code => $perms) {
            $role = Role::query()->whereNull('tenant_id')->where('code', $code)->first();
            if ($role === null) {
                continue;
            }

            $role->permissions = array_values(array_unique(array_merge(
                (array) $role->permissions,
                $perms,
            )));
            $role->save();
        }
    }

    public function down(): void
    {
        $allBomPerms = array_unique(array_merge(...array_values($this->rolePerms)));

        foreach (array_keys($this->rolePerms) as $code) {
            $role = Role::query()->whereNull('tenant_id')->where('code', $code)->first();
            if ($role === null) {
                continue;
            }

            $role->permissions = array_values(array_diff(
                (array) $role->permissions,
                $allBomPerms,
            ));
            $role->save();
        }
    }
};
```

- [ ] **Step 1.3: 写 / 改 PermissionSeedTest**

打开 `app/Modules/Inventory/Tests/Feature/PermissionSeedTest.php`，把第一个 test 名从 `'Permission enum contains all 16 inventory cases'` 改为 `'Permission enum contains all 22 tenant cases'`，期望数组扩展：

```php
test('Permission enum contains all 22 tenant cases', function () {
    $expected = [
        'items.read', 'items.write',
        'item_skus.read', 'item_skus.write',
        'categories.read', 'categories.write',
        'inventory.read', 'inventory.adjust',
        'stocktake.write', 'damage.write',
        'stock_txn.read', 'stock_txn.reverse',
        'inventory_config.read', 'inventory_config.update',
        'inventory_policy.read', 'inventory_policy.update',
        'boms.read', 'boms.create', 'boms.update', 'boms.delete',
        'production.execute', 'production.read',
    ];
    $values = array_column(Permission::cases(), 'value');
    foreach ($expected as $perm) {
        expect($values)->toContain($perm);
    }
});
```

并在文件末尾追加 3 个新角色断言：

```php
test('TenantAdmin role contains all 6 BOM/produce permissions', function () {
    $role = Role::query()->whereNull('tenant_id')->where('code', 'TenantAdmin')->firstOrFail();

    foreach (['boms.read', 'boms.create', 'boms.update', 'boms.delete',
              'production.execute', 'production.read'] as $perm) {
        expect($role->permissions)->toContain($perm);
    }
});

test('StoreManager has BOM CRU + production but not boms.delete', function () {
    $role = Role::query()->whereNull('tenant_id')->where('code', 'StoreManager')->firstOrFail();

    expect($role->permissions)->toContain('boms.read');
    expect($role->permissions)->toContain('boms.create');
    expect($role->permissions)->toContain('boms.update');
    expect($role->permissions)->toContain('production.execute');
    expect($role->permissions)->toContain('production.read');
    expect($role->permissions)->not->toContain('boms.delete');
});

test('StoreClerk has only boms.read + production.read + production.execute', function () {
    $role = Role::query()->whereNull('tenant_id')->where('code', 'StoreClerk')->firstOrFail();

    expect($role->permissions)->toContain('boms.read');
    expect($role->permissions)->toContain('production.read');
    expect($role->permissions)->toContain('production.execute');
    expect($role->permissions)->not->toContain('boms.create');
    expect($role->permissions)->not->toContain('boms.update');
    expect($role->permissions)->not->toContain('boms.delete');
});
```

- [ ] **Step 1.4: 跑测试看是否有回归**

```bash
./vendor/bin/pest --filter='Permission' 2>&1 | tail -10
./vendor/bin/pest --filter='SystemRoles\|MultiBindingMerge' 2>&1 | tail -10
```

期待：PermissionSeedTest 全过；如果 SystemRolesSeedTest / MultiBindingMergeTest 失败（因为它们硬编码了角色的全部权限数组），找到对应文件，把 hardcoded 期望数组扩展加上 6 个新权限。grep 命令：

```bash
grep -rn "boms.read\|production.execute\|stocktake.write" app/Modules/Authorization/Tests/ 2>&1
```

针对找到的每个测试文件，按角色对应扩展（参考前一阶段 inventory perm 加入时的修法）：
- TenantAdmin 角色的期望数组追加 6 项
- StoreManager 期望数组追加 5 项（不含 boms.delete）
- StoreClerk 期望数组追加 3 项

- [ ] **Step 1.5: 跑全量测试确认无新增失败**

```bash
./vendor/bin/pest 2>&1 | tail -5
```

期待：290 + 4（PermissionSeed 新增）= 294 passed，无 failure。

- [ ] **Step 1.6: Commit**

```bash
git add app/Modules/Authorization/Enums/Permission.php \
        database/migrations/2026_05_09_000001_seed_bom_produce_permissions.php \
        app/Modules/Inventory/Tests/Feature/PermissionSeedTest.php \
        app/Modules/Authorization/Tests/  # 任何被改动的测试
git commit -m "feat(authz): permissions for BOM CRUD + production.execute/read"
```

---

## Task 2: BOM CRUD（控制器 + 3 Vue 页 + 测试）

**Files:**
- Create: `app/Modules/Inventory/Http/Controllers/Web/TenantBomController.php`
- Create: `resources/js/Pages/tenant/Bom/Index.vue`
- Create: `resources/js/Pages/tenant/Bom/Create.vue`
- Create: `resources/js/Pages/tenant/Bom/Edit.vue`
- Modify: `routes/web.php`（追加 6 条 BOM 路由）
- Create: `app/Modules/Inventory/Tests/Feature/BomSchemaTest.php`
- Create: `app/Modules/Inventory/Tests/Feature/BomComponentSchemaTest.php`
- Create: `app/Modules/Inventory/Tests/Feature/TenantBomWebTest.php`

### 2.1 Schema 测试（先写 schema 测试落地基础事实）

- [ ] **Step 2.1.1: 写 BomSchemaTest**

`app/Modules/Inventory/Tests/Feature/BomSchemaTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Inventory\Models\Bom;
use App\Modules\Inventory\Models\BomComponent;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('boms table has expected columns', function () {
    $cols = Schema::getColumnListing('boms');
    foreach ([
        'id', 'tenant_id', 'output_sku_id', 'output_qty', 'bom_type',
        'store_id', 'status', 'created_at', 'updated_at', 'deleted_at',
    ] as $c) {
        expect($cols)->toContain($c);
    }
});

test('Bom model uses HasUlid + BelongsToTenant + SoftDeletes', function () {
    $tenant = Tenant::factory()->create();
    CurrentTenant::set($tenant->id);
    $sku = ItemSku::factory()->create(['tenant_id' => $tenant->id]);
    $bom = Bom::factory()->create([
        'tenant_id' => $tenant->id,
        'output_sku_id' => $sku->id,
    ]);

    expect($bom->id)->toHaveLength(26);
    expect($bom->bom_type->value)->toBe('STANDARD');
    expect($bom->output_qty)->toBe('1.0000');

    $bom->delete();
    expect(Bom::query()->find($bom->id))->toBeNull();
    expect(Bom::query()->withTrashed()->find($bom->id))->not->toBeNull();
});

test('Bom outputSku relation works', function () {
    $tenant = Tenant::factory()->create();
    CurrentTenant::set($tenant->id);
    $sku = ItemSku::factory()->create(['tenant_id' => $tenant->id]);
    $bom = Bom::factory()->create(['tenant_id' => $tenant->id, 'output_sku_id' => $sku->id]);

    expect($bom->outputSku->id)->toBe($sku->id);
});

test('Bom components hasMany works', function () {
    $tenant = Tenant::factory()->create();
    CurrentTenant::set($tenant->id);
    $bom = Bom::factory()->create(['tenant_id' => $tenant->id]);
    $compSku = ItemSku::factory()->create(['tenant_id' => $tenant->id]);
    BomComponent::factory()->create(['bom_id' => $bom->id, 'component_sku_id' => $compSku->id]);

    expect($bom->components)->toHaveCount(1);
});

test('BelongsToTenant scopes Bom by current tenant', function () {
    $t1 = Tenant::factory()->create();
    $t2 = Tenant::factory()->create();
    CurrentTenant::set($t1->id);
    Bom::factory()->create(['tenant_id' => $t1->id]);
    Bom::factory()->create(['tenant_id' => $t2->id]);
    expect(Bom::query()->count())->toBe(1);

    CurrentTenant::set($t2->id);
    expect(Bom::query()->count())->toBe(1);
});
```

- [ ] **Step 2.1.2: 写 BomComponentSchemaTest**

`app/Modules/Inventory/Tests/Feature/BomComponentSchemaTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Inventory\Models\Bom;
use App\Modules\Inventory\Models\BomComponent;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('bom_components table has expected columns', function () {
    $cols = Schema::getColumnListing('bom_components');
    foreach ([
        'id', 'bom_id', 'component_sku_id', 'consume_qty', 'loss_rate', 'sequence_no',
    ] as $c) {
        expect($cols)->toContain($c);
    }
});

test('BomComponent casts are decimal:4 + int', function () {
    $tenant = Tenant::factory()->create();
    CurrentTenant::set($tenant->id);
    $bom = Bom::factory()->create(['tenant_id' => $tenant->id]);
    $sku = ItemSku::factory()->create(['tenant_id' => $tenant->id]);
    $c = BomComponent::factory()->create([
        'bom_id' => $bom->id, 'component_sku_id' => $sku->id,
        'consume_qty' => '10.5', 'loss_rate' => '0.1', 'sequence_no' => 5,
    ]);
    expect($c->consume_qty)->toBe('10.5000');
    expect($c->loss_rate)->toBe('0.1000');
    expect($c->sequence_no)->toBe(5);
});

test('BomComponent componentSku + bom relations work', function () {
    $tenant = Tenant::factory()->create();
    CurrentTenant::set($tenant->id);
    $bom = Bom::factory()->create(['tenant_id' => $tenant->id]);
    $sku = ItemSku::factory()->create(['tenant_id' => $tenant->id]);
    $c = BomComponent::factory()->create(['bom_id' => $bom->id, 'component_sku_id' => $sku->id]);

    expect($c->bom->id)->toBe($bom->id);
    expect($c->componentSku->id)->toBe($sku->id);
});

test('bom_components cascade on bom delete', function () {
    $tenant = Tenant::factory()->create();
    CurrentTenant::set($tenant->id);
    $bom = Bom::factory()->create(['tenant_id' => $tenant->id]);
    $sku = ItemSku::factory()->create(['tenant_id' => $tenant->id]);
    BomComponent::factory()->create(['bom_id' => $bom->id, 'component_sku_id' => $sku->id]);

    // 物理删 bom（Bom 是 soft delete，所以走 forceDelete 测 cascade）
    $bom->forceDelete();
    expect(BomComponent::query()->count())->toBe(0);
});
```

- [ ] **Step 2.1.3: 跑两个 schema 测试**

```bash
./vendor/bin/pest --filter='BomSchema\|BomComponentSchema' 2>&1 | tail -10
```

期待：全过（这两个都基于已有 migration / model，应该直接绿）。

### 2.2 控制器 + 路由

- [ ] **Step 2.2.1: 写 TenantBomController（先骨架）**

`app/Modules/Inventory/Http/Controllers/Web/TenantBomController.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers\Web;

use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Inventory\Models\Bom;
use App\Modules\Inventory\Models\BomComponent;
use App\Modules\Tenancy\Models\Store;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TenantBomController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $this->requireCurrentTenant($request);
        $bomType = $request->query('bom_type');

        $boms = Bom::query()
            ->with(['outputSku.item', 'components'])
            ->when($bomType, fn ($q) => $q->where('bom_type', $bomType))
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('tenant/Bom/Index', [
            'boms' => $boms,
            'filterBomType' => $bomType,
        ]);
    }

    public function create(Request $request): Response
    {
        $tenantId = $this->requireCurrentTenant($request);
        return Inertia::render('tenant/Bom/Create', $this->formProps($tenantId));
    }

    public function storeFromForm(Request $request): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        $data = $this->validateForm($request, $tenantId, null);

        DB::transaction(function () use ($tenantId, $data) {
            $bom = Bom::query()->create([
                'tenant_id' => $tenantId,
                'output_sku_id' => $data['output_sku_id'],
                'output_qty' => (string) $data['output_qty'],
                'bom_type' => $data['bom_type'],
                'store_id' => $data['store_id'] ?? null,
                'status' => $data['status'],
            ]);

            foreach ($data['components'] as $row) {
                BomComponent::query()->create([
                    'bom_id' => $bom->id,
                    'component_sku_id' => $row['component_sku_id'],
                    'consume_qty' => (string) $row['consume_qty'],
                    'loss_rate' => (string) $row['loss_rate'],
                    'sequence_no' => (int) $row['sequence_no'],
                ]);
            }
        });

        return redirect('/tenant/boms')->with('success', '配方已创建');
    }

    public function edit(Request $request, string $id): Response
    {
        $tenantId = $this->requireCurrentTenant($request);
        $bom = Bom::query()->with('components')->findOrFail($id);

        return Inertia::render('tenant/Bom/Edit', array_merge(
            $this->formProps($tenantId),
            ['bom' => $bom]
        ));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        $bom = Bom::query()->findOrFail($id);
        $data = $this->validateForm($request, $tenantId, $bom->id);

        DB::transaction(function () use ($bom, $data) {
            $bom->update([
                'output_sku_id' => $data['output_sku_id'],
                'output_qty' => (string) $data['output_qty'],
                'bom_type' => $data['bom_type'],
                'store_id' => $data['store_id'] ?? null,
                'status' => $data['status'],
            ]);

            BomComponent::query()->where('bom_id', $bom->id)->delete();
            foreach ($data['components'] as $row) {
                BomComponent::query()->create([
                    'bom_id' => $bom->id,
                    'component_sku_id' => $row['component_sku_id'],
                    'consume_qty' => (string) $row['consume_qty'],
                    'loss_rate' => (string) $row['loss_rate'],
                    'sequence_no' => (int) $row['sequence_no'],
                ]);
            }
        });

        return redirect('/tenant/boms')->with('success', '配方已更新');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->requireCurrentTenant($request);
        $bom = Bom::query()->findOrFail($id);
        $bom->delete();

        return redirect('/tenant/boms')->with('success', '配方已删除');
    }

    /**
     * 提供给 create / edit 表单的下拉数据。
     *
     * @return array{outputSkus: \Illuminate\Support\Collection, componentSkus: \Illuminate\Support\Collection, stores: \Illuminate\Support\Collection}
     */
    private function formProps(string $tenantId): array
    {
        return [
            'outputSkus' => ItemSku::query()->with('item')
                ->whereHas('item', fn ($q) => $q->whereIn('item_type',
                    ['SALE_PRODUCT', 'FINISHED_GOOD', 'SEMI_FINISHED']))
                ->get(['id', 'item_id', 'sku_code', 'spec_name']),
            'componentSkus' => ItemSku::query()->with('item')
                ->whereHas('item', fn ($q) => $q->whereIn('item_type',
                    ['RAW_MATERIAL', 'SEMI_FINISHED', 'PACKAGE']))
                ->get(['id', 'item_id', 'sku_code', 'spec_name']),
            'stores' => Store::query()->where('tenant_id', $tenantId)
                ->get(['id', 'name']),
        ];
    }

    /**
     * @return array{output_sku_id:string,output_qty:numeric-string|float|int,bom_type:string,store_id:?string,status:string,components:array<int, array{component_sku_id:string,consume_qty:numeric-string|float|int,loss_rate:numeric-string|float|int,sequence_no:int}>}
     */
    private function validateForm(Request $request, string $tenantId, ?string $excludeBomId): array
    {
        $rules = [
            'output_sku_id' => ['required', 'string', 'size:26',
                Rule::exists('item_skus', 'id')->where('tenant_id', $tenantId)],
            'output_qty' => ['required', 'numeric', 'gt:0'],
            'bom_type' => ['required', Rule::in(['STANDARD', 'STORE_CUSTOM'])],
            'store_id' => [
                Rule::requiredIf(fn () => $request->input('bom_type') === 'STORE_CUSTOM'),
                Rule::prohibitedIf(fn () => $request->input('bom_type') === 'STANDARD'),
                'nullable', 'string', 'size:26',
                Rule::exists('stores', 'id')->where('tenant_id', $tenantId),
            ],
            'status' => ['required', Rule::in(['active', 'disabled'])],
            'components' => ['required', 'array', 'min:1'],
            'components.*.component_sku_id' => ['required', 'string', 'size:26',
                Rule::exists('item_skus', 'id')->where('tenant_id', $tenantId)],
            'components.*.consume_qty' => ['required', 'numeric', 'gt:0'],
            'components.*.loss_rate' => ['required', 'numeric', 'gte:0', 'lte:1'],
            'components.*.sequence_no' => ['required', 'integer', 'gte:0'],
        ];

        $data = $request->validate($rules);

        // 业务校验：item_type / 自引用 / 唯一性
        $errors = [];
        $outputSku = ItemSku::query()->with('item')->find($data['output_sku_id']);
        if ($outputSku && ! in_array($outputSku->item->item_type->value,
            ['SALE_PRODUCT', 'FINISHED_GOOD', 'SEMI_FINISHED'], true)) {
            $errors['output_sku_id'] = ['产出 SKU 必须是销售品 / 成品 / 半成品类型'];
        }

        foreach ($data['components'] as $i => $row) {
            if ($row['component_sku_id'] === $data['output_sku_id']) {
                $errors["components.{$i}.component_sku_id"] = ['组件 SKU 不能等于产出 SKU'];
                continue;
            }
            $compSku = ItemSku::query()->with('item')->find($row['component_sku_id']);
            if ($compSku && ! in_array($compSku->item->item_type->value,
                ['RAW_MATERIAL', 'SEMI_FINISHED', 'PACKAGE'], true)) {
                $errors["components.{$i}.component_sku_id"] = ['组件 SKU 必须是原料 / 半成品 / 包材类型'];
            }
        }

        // 唯一性：同 (output_sku, bom_type, store_id, status='active', deleted_at NULL) 至多 1 条
        if ($data['status'] === 'active') {
            $dupQuery = Bom::query()
                ->where('tenant_id', $tenantId)
                ->where('output_sku_id', $data['output_sku_id'])
                ->where('bom_type', $data['bom_type'])
                ->where('status', 'active');
            if ($data['bom_type'] === 'STORE_CUSTOM') {
                $dupQuery->where('store_id', $data['store_id']);
            } else {
                $dupQuery->whereNull('store_id');
            }
            if ($excludeBomId) {
                $dupQuery->whereKeyNot($excludeBomId);
            }
            if ($dupQuery->exists()) {
                $errors['output_sku_id'] = ['同产出 SKU 同类型已有 active 配方'];
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }

    private function requireCurrentTenant(Request $request): string
    {
        $id = CurrentTenant::id();
        abort_if($id === null, 403, '未绑定租户');
        return $id;
    }
}
```

- [ ] **Step 2.2.2: 在 routes/web.php 注册 6 条 BOM 路由**

`routes/web.php` 顶部 imports 区追加：
```php
use App\Modules\Inventory\Http\Controllers\Web\TenantBomController;
```

在 `Route::prefix('tenant')->middleware('auth')->group(...)` 内（参考 `categories` / `items` 块的位置；放在 `items` 之后、`settings/inventory` 之前）追加：
```php
    Route::get('boms', [TenantBomController::class, 'index']);
    Route::get('boms/create', [TenantBomController::class, 'create']);
    Route::post('boms', [TenantBomController::class, 'storeFromForm']);
    Route::get('boms/{id}/edit', [TenantBomController::class, 'edit']);
    Route::patch('boms/{id}', [TenantBomController::class, 'update']);
    Route::delete('boms/{id}', [TenantBomController::class, 'destroy']);
```

### 2.3 Web 测试

- [ ] **Step 2.3.1: 写 TenantBomWebTest（先全部测试，逐步让它通过）**

`app/Modules/Inventory/Tests/Feature/TenantBomWebTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\Membership;
use App\Modules\Authorization\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Inventory\Models\Bom;
use App\Modules\Inventory\Models\BomComponent;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * 创建一个属于指定 item_type 的 SKU（item + sku 一并生成）。
 */
function makeSku(string $tenantId, string $itemType): ItemSku
{
    $cat = Category::factory()->create([
        'tenant_id' => $tenantId, 'owner_type' => 'TENANT', 'owner_store_id' => null,
    ]);
    $item = Item::factory()->create([
        'tenant_id' => $tenantId,
        'item_type' => $itemType,
        'business_category_id' => $cat->id,
    ]);
    return ItemSku::factory()->create([
        'tenant_id' => $tenantId,
        'item_id' => $item->id,
    ]);
}

/**
 * 登录一个 tenant 域用户（auth 中间件需要）。
 */
function actingAsTenantUser(Tenant $tenant): User
{
    $user = User::factory()->create();
    Membership::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
    ]);
    test()->actingAs($user);
    CurrentTenant::set($tenant->id);
    return $user;
}

test('GET /tenant/boms 列表只展示当前 tenant 的 BOM', function () {
    $t1 = Tenant::factory()->create();
    $t2 = Tenant::factory()->create();
    actingAsTenantUser($t1);

    $sku1 = makeSku($t1->id, 'SALE_PRODUCT');
    Bom::factory()->create(['tenant_id' => $t1->id, 'output_sku_id' => $sku1->id]);

    CurrentTenant::set($t2->id);
    $sku2 = makeSku($t2->id, 'SALE_PRODUCT');
    Bom::factory()->create(['tenant_id' => $t2->id, 'output_sku_id' => $sku2->id]);

    CurrentTenant::set($t1->id);
    $resp = test()->get('/tenant/boms');
    $resp->assertOk();
    $resp->assertInertia(fn ($p) => $p->component('tenant/Bom/Index')
        ->where('boms.total', 1));
});

test('POST /tenant/boms 合法 → 创建 BOM + components', function () {
    $tenant = Tenant::factory()->create();
    actingAsTenantUser($tenant);
    $output = makeSku($tenant->id, 'SALE_PRODUCT');
    $comp1 = makeSku($tenant->id, 'RAW_MATERIAL');
    $comp2 = makeSku($tenant->id, 'PACKAGE');

    $resp = test()->post('/tenant/boms', [
        'output_sku_id' => $output->id,
        'output_qty' => 1,
        'bom_type' => 'STANDARD',
        'status' => 'active',
        'components' => [
            ['component_sku_id' => $comp1->id, 'consume_qty' => 10, 'loss_rate' => 0.1, 'sequence_no' => 1],
            ['component_sku_id' => $comp2->id, 'consume_qty' => 1, 'loss_rate' => 0, 'sequence_no' => 2],
        ],
    ]);
    $resp->assertRedirect('/tenant/boms');
    $bom = Bom::query()->where('output_sku_id', $output->id)->firstOrFail();
    expect($bom->components()->count())->toBe(2);
});

test('POST /tenant/boms 产出 SKU item_type=RAW_MATERIAL → 422', function () {
    $tenant = Tenant::factory()->create();
    actingAsTenantUser($tenant);
    $bad = makeSku($tenant->id, 'RAW_MATERIAL');
    $comp = makeSku($tenant->id, 'RAW_MATERIAL');

    $resp = test()->post('/tenant/boms', [
        'output_sku_id' => $bad->id,
        'output_qty' => 1, 'bom_type' => 'STANDARD', 'status' => 'active',
        'components' => [['component_sku_id' => $comp->id, 'consume_qty' => 1, 'loss_rate' => 0, 'sequence_no' => 0]],
    ]);
    $resp->assertSessionHasErrors('output_sku_id');
});

test('POST /tenant/boms 组件 item_type=SALE_PRODUCT → 422', function () {
    $tenant = Tenant::factory()->create();
    actingAsTenantUser($tenant);
    $output = makeSku($tenant->id, 'FINISHED_GOOD');
    $bad = makeSku($tenant->id, 'SALE_PRODUCT');

    $resp = test()->post('/tenant/boms', [
        'output_sku_id' => $output->id,
        'output_qty' => 1, 'bom_type' => 'STANDARD', 'status' => 'active',
        'components' => [['component_sku_id' => $bad->id, 'consume_qty' => 1, 'loss_rate' => 0, 'sequence_no' => 0]],
    ]);
    $resp->assertSessionHasErrors('components.0.component_sku_id');
});

test('POST /tenant/boms 组件等于产出 SKU → 422', function () {
    $tenant = Tenant::factory()->create();
    actingAsTenantUser($tenant);
    $sku = makeSku($tenant->id, 'SEMI_FINISHED');

    $resp = test()->post('/tenant/boms', [
        'output_sku_id' => $sku->id,
        'output_qty' => 1, 'bom_type' => 'STANDARD', 'status' => 'active',
        'components' => [['component_sku_id' => $sku->id, 'consume_qty' => 1, 'loss_rate' => 0, 'sequence_no' => 0]],
    ]);
    $resp->assertSessionHasErrors('components.0.component_sku_id');
});

test('POST /tenant/boms STANDARD + store_id → 422 prohibited', function () {
    $tenant = Tenant::factory()->create();
    actingAsTenantUser($tenant);
    $store = Store::factory()->create(['tenant_id' => $tenant->id]);
    $output = makeSku($tenant->id, 'SALE_PRODUCT');
    $comp = makeSku($tenant->id, 'RAW_MATERIAL');

    $resp = test()->post('/tenant/boms', [
        'output_sku_id' => $output->id,
        'output_qty' => 1, 'bom_type' => 'STANDARD', 'status' => 'active',
        'store_id' => $store->id,
        'components' => [['component_sku_id' => $comp->id, 'consume_qty' => 1, 'loss_rate' => 0, 'sequence_no' => 0]],
    ]);
    $resp->assertSessionHasErrors('store_id');
});

test('POST /tenant/boms STORE_CUSTOM 无 store_id → 422 required', function () {
    $tenant = Tenant::factory()->create();
    actingAsTenantUser($tenant);
    $output = makeSku($tenant->id, 'SALE_PRODUCT');
    $comp = makeSku($tenant->id, 'RAW_MATERIAL');

    $resp = test()->post('/tenant/boms', [
        'output_sku_id' => $output->id,
        'output_qty' => 1, 'bom_type' => 'STORE_CUSTOM', 'status' => 'active',
        'components' => [['component_sku_id' => $comp->id, 'consume_qty' => 1, 'loss_rate' => 0, 'sequence_no' => 0]],
    ]);
    $resp->assertSessionHasErrors('store_id');
});

test('POST /tenant/boms 同 (output_sku, type, status=active) 重复 → 422', function () {
    $tenant = Tenant::factory()->create();
    actingAsTenantUser($tenant);
    $output = makeSku($tenant->id, 'SALE_PRODUCT');
    $comp = makeSku($tenant->id, 'RAW_MATERIAL');
    Bom::factory()->create([
        'tenant_id' => $tenant->id, 'output_sku_id' => $output->id,
        'bom_type' => 'STANDARD', 'store_id' => null, 'status' => 'active',
    ]);

    $resp = test()->post('/tenant/boms', [
        'output_sku_id' => $output->id,
        'output_qty' => 1, 'bom_type' => 'STANDARD', 'status' => 'active',
        'components' => [['component_sku_id' => $comp->id, 'consume_qty' => 1, 'loss_rate' => 0, 'sequence_no' => 0]],
    ]);
    $resp->assertSessionHasErrors('output_sku_id');
});

test('POST /tenant/boms 跨租户 output_sku → 422', function () {
    $tenant = Tenant::factory()->create();
    $other = Tenant::factory()->create();
    actingAsTenantUser($tenant);
    $crossSku = makeSku($other->id, 'SALE_PRODUCT');
    $comp = makeSku($tenant->id, 'RAW_MATERIAL');

    $resp = test()->post('/tenant/boms', [
        'output_sku_id' => $crossSku->id,
        'output_qty' => 1, 'bom_type' => 'STANDARD', 'status' => 'active',
        'components' => [['component_sku_id' => $comp->id, 'consume_qty' => 1, 'loss_rate' => 0, 'sequence_no' => 0]],
    ]);
    $resp->assertSessionHasErrors('output_sku_id');
});

test('PATCH /tenant/boms/{id} 覆盖 components（先删后建）', function () {
    $tenant = Tenant::factory()->create();
    actingAsTenantUser($tenant);
    $output = makeSku($tenant->id, 'SALE_PRODUCT');
    $comp1 = makeSku($tenant->id, 'RAW_MATERIAL');
    $comp2 = makeSku($tenant->id, 'PACKAGE');
    $bom = Bom::factory()->create(['tenant_id' => $tenant->id, 'output_sku_id' => $output->id]);
    BomComponent::factory()->create(['bom_id' => $bom->id, 'component_sku_id' => $comp1->id]);

    $resp = test()->patch('/tenant/boms/' . $bom->id, [
        'output_sku_id' => $output->id,
        'output_qty' => 2, 'bom_type' => 'STANDARD', 'status' => 'active',
        'components' => [
            ['component_sku_id' => $comp2->id, 'consume_qty' => 5, 'loss_rate' => 0, 'sequence_no' => 0],
        ],
    ]);
    $resp->assertRedirect('/tenant/boms');
    expect($bom->components()->count())->toBe(1);
    expect($bom->components()->first()->component_sku_id)->toBe($comp2->id);
});

test('DELETE /tenant/boms/{id} 软删后从列表消失', function () {
    $tenant = Tenant::factory()->create();
    actingAsTenantUser($tenant);
    $output = makeSku($tenant->id, 'SALE_PRODUCT');
    $bom = Bom::factory()->create(['tenant_id' => $tenant->id, 'output_sku_id' => $output->id]);

    test()->delete('/tenant/boms/' . $bom->id)->assertRedirect('/tenant/boms');

    $resp = test()->get('/tenant/boms');
    $resp->assertInertia(fn ($p) => $p->where('boms.total', 0));
});

test('编辑跨租户 BOM → 404', function () {
    $tenant = Tenant::factory()->create();
    $other = Tenant::factory()->create();
    actingAsTenantUser($tenant);
    $output = makeSku($other->id, 'SALE_PRODUCT');
    $crossBom = Bom::factory()->create(['tenant_id' => $other->id, 'output_sku_id' => $output->id]);

    test()->get('/tenant/boms/' . $crossBom->id . '/edit')->assertNotFound();
});
```

- [ ] **Step 2.3.2: 跑 Web 测试**

```bash
./vendor/bin/pest --filter='TenantBomWebTest' 2>&1 | tail -20
```

期待：12 个用例全部 PASS。如果 `actingAsTenantUser` / `makeSku` 报错（依赖具体 Membership / Item factory 字段），按报错调 helper（这通常涉及 catalog 模块 factory 字段差异，参照 `TenantItemWebTest.php` 的同名 helper）。

### 2.4 Vue 页

- [ ] **Step 2.4.1: 写 Bom/Index.vue**

`resources/js/Pages/tenant/Bom/Index.vue`：

```vue
<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { ElButton, ElTable, ElTableColumn, ElTabs, ElTabPane, ElPagination, ElTag, ElPopconfirm } from 'element-plus'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

interface BomRow {
  id: string
  output_sku: { id: string; sku_code: string; spec_name: string; item: { name: string } }
  output_qty: string
  bom_type: 'STANDARD' | 'STORE_CUSTOM'
  store_id: string | null
  status: 'active' | 'disabled'
  components: { id: string }[]
}

interface Props {
  boms: { data: BomRow[]; current_page: number; last_page: number; per_page: number; total: number }
  filterBomType?: 'STANDARD' | 'STORE_CUSTOM' | null
}

const props = defineProps<Props>()
const { t } = useI18n()
const activeTab = ref<string>(props.filterBomType ?? 'STANDARD')

function changeTab(tab: string) {
  router.get('/tenant/boms', { bom_type: tab }, { preserveState: false })
}

function destroy(id: string) {
  router.delete('/tenant/boms/' + id)
}

function pageTo(page: number) {
  router.get('/tenant/boms', { page, bom_type: activeTab.value }, { preserveState: false })
}
</script>

<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-2xl font-bold">{{ t('nav.inventory.boms') }}</h1>
      <Link href="/tenant/boms/create">
        <ElButton type="primary">+ 新建配方</ElButton>
      </Link>
    </div>

    <ElTabs :model-value="activeTab" @update:model-value="(v) => changeTab(String(v))">
      <ElTabPane label="租户公共 (STANDARD)" name="STANDARD" />
      <ElTabPane label="门店私有 (STORE_CUSTOM)" name="STORE_CUSTOM" />
    </ElTabs>

    <ElTable :data="boms.data" border>
      <ElTableColumn label="产出 SKU" min-width="200">
        <template #default="{ row }">
          {{ row.output_sku.item.name }} / {{ row.output_sku.spec_name }}
        </template>
      </ElTableColumn>
      <ElTableColumn label="产出量" prop="output_qty" width="100" />
      <ElTableColumn label="组件数" width="100">
        <template #default="{ row }">{{ row.components.length }}</template>
      </ElTableColumn>
      <ElTableColumn label="状态" width="100">
        <template #default="{ row }">
          <ElTag :type="row.status === 'active' ? 'success' : 'info'">{{ row.status }}</ElTag>
        </template>
      </ElTableColumn>
      <ElTableColumn label="操作" width="200">
        <template #default="{ row }">
          <Link :href="'/tenant/boms/' + row.id + '/edit'">
            <ElButton size="small">编辑</ElButton>
          </Link>
          <ElPopconfirm title="确认删除？" @confirm="destroy(row.id)">
            <template #reference>
              <ElButton size="small" type="danger">删除</ElButton>
            </template>
          </ElPopconfirm>
        </template>
      </ElTableColumn>
    </ElTable>

    <div class="mt-4 flex justify-end">
      <ElPagination
        :current-page="boms.current_page"
        :page-count="boms.last_page"
        :page-size="boms.per_page"
        :total="boms.total"
        layout="prev, pager, next"
        @current-change="pageTo"
      />
    </div>
  </div>
</template>
```

- [ ] **Step 2.4.2: 写 Bom/Create.vue**

`resources/js/Pages/tenant/Bom/Create.vue`：

```vue
<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core'
import { router } from '@inertiajs/vue3'
import { ElButton, ElForm, ElFormItem, ElInputNumber, ElOption, ElRadio, ElRadioGroup, ElSelect } from 'element-plus'
import { computed, reactive } from 'vue'

interface SkuOption {
  id: string
  sku_code: string
  spec_name: string
  item: { id: string; name: string }
}
interface StoreOption { id: string; name: string }

interface Props {
  outputSkus: SkuOption[]
  componentSkus: SkuOption[]
  stores: StoreOption[]
}
defineProps<Props>()

const form = reactive({
  output_sku_id: '',
  output_qty: 1,
  bom_type: 'STANDARD' as 'STANDARD' | 'STORE_CUSTOM',
  store_id: null as string | null,
  status: 'active' as 'active' | 'disabled',
  components: [
    { component_sku_id: '', consume_qty: 1, loss_rate: 0, sequence_no: 0 },
  ],
})

const isStoreCustom = computed(() => form.bom_type === 'STORE_CUSTOM')

function addRow() {
  form.components.push({ component_sku_id: '', consume_qty: 1, loss_rate: 0, sequence_no: form.components.length })
}
function removeRow(idx: number) {
  form.components.splice(idx, 1)
}

function submit() {
  if (form.bom_type === 'STANDARD') form.store_id = null
  router.post('/tenant/boms', form as unknown as Record<string, FormDataConvertible>)
}
</script>

<template>
  <div class="p-6 max-w-4xl">
    <h1 class="text-2xl font-bold mb-4">新建配方</h1>

    <ElForm :model="form" label-width="120px">
      <ElFormItem label="产出 SKU">
        <ElSelect v-model="form.output_sku_id" filterable placeholder="选择销售品 / 成品 / 半成品">
          <ElOption
            v-for="sku in outputSkus"
            :key="sku.id"
            :label="sku.item.name + ' / ' + sku.spec_name"
            :value="sku.id"
          />
        </ElSelect>
      </ElFormItem>

      <ElFormItem label="产出数量">
        <ElInputNumber v-model="form.output_qty" :min="0.0001" :step="1" :precision="4" />
      </ElFormItem>

      <ElFormItem label="配方类型">
        <ElRadioGroup v-model="form.bom_type">
          <ElRadio value="STANDARD">租户公共</ElRadio>
          <ElRadio value="STORE_CUSTOM">门店私有</ElRadio>
        </ElRadioGroup>
      </ElFormItem>

      <ElFormItem v-if="isStoreCustom" label="所属门店">
        <ElSelect v-model="form.store_id" placeholder="选择门店">
          <ElOption v-for="s in stores" :key="s.id" :label="s.name" :value="s.id" />
        </ElSelect>
      </ElFormItem>

      <ElFormItem label="状态">
        <ElRadioGroup v-model="form.status">
          <ElRadio value="active">启用</ElRadio>
          <ElRadio value="disabled">停用</ElRadio>
        </ElRadioGroup>
      </ElFormItem>

      <h2 class="text-lg font-semibold mt-6 mb-2">组件清单</h2>
      <table class="w-full border">
        <thead>
          <tr class="bg-gray-50">
            <th class="p-2 border">组件 SKU</th>
            <th class="p-2 border w-32">单份用量</th>
            <th class="p-2 border w-32">损耗率 (0~1)</th>
            <th class="p-2 border w-24">顺序</th>
            <th class="p-2 border w-20">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, idx) in form.components" :key="idx">
            <td class="p-2 border">
              <ElSelect v-model="row.component_sku_id" filterable placeholder="选择原料 / 半成品 / 包材">
                <ElOption
                  v-for="sku in componentSkus"
                  :key="sku.id"
                  :label="sku.item.name + ' / ' + sku.spec_name"
                  :value="sku.id"
                />
              </ElSelect>
            </td>
            <td class="p-2 border">
              <ElInputNumber v-model="row.consume_qty" :min="0.0001" :precision="4" />
            </td>
            <td class="p-2 border">
              <ElInputNumber v-model="row.loss_rate" :min="0" :max="1" :step="0.01" :precision="4" />
            </td>
            <td class="p-2 border">
              <ElInputNumber v-model="row.sequence_no" :min="0" :step="1" />
            </td>
            <td class="p-2 border text-center">
              <ElButton size="small" type="danger" @click="removeRow(idx)">删除</ElButton>
            </td>
          </tr>
        </tbody>
      </table>
      <ElButton class="mt-2" @click="addRow">+ 添加组件</ElButton>

      <div class="mt-6">
        <ElButton type="primary" @click="submit">保存</ElButton>
      </div>
    </ElForm>
  </div>
</template>
```

- [ ] **Step 2.4.3: 写 Bom/Edit.vue**

`resources/js/Pages/tenant/Bom/Edit.vue`：

```vue
<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core'
import { router } from '@inertiajs/vue3'
import { ElButton, ElForm, ElFormItem, ElInputNumber, ElOption, ElRadio, ElRadioGroup, ElSelect } from 'element-plus'
import { computed, reactive } from 'vue'

interface SkuOption {
  id: string
  sku_code: string
  spec_name: string
  item: { id: string; name: string }
}
interface StoreOption { id: string; name: string }
interface BomRow {
  id: string
  output_sku_id: string
  output_qty: string
  bom_type: 'STANDARD' | 'STORE_CUSTOM'
  store_id: string | null
  status: 'active' | 'disabled'
  components: Array<{ id: string; component_sku_id: string; consume_qty: string; loss_rate: string; sequence_no: number }>
}

interface Props {
  bom: BomRow
  outputSkus: SkuOption[]
  componentSkus: SkuOption[]
  stores: StoreOption[]
}
const props = defineProps<Props>()

const form = reactive({
  output_sku_id: props.bom.output_sku_id,
  output_qty: Number(props.bom.output_qty),
  bom_type: props.bom.bom_type,
  store_id: props.bom.store_id,
  status: props.bom.status,
  components: props.bom.components.map(c => ({
    component_sku_id: c.component_sku_id,
    consume_qty: Number(c.consume_qty),
    loss_rate: Number(c.loss_rate),
    sequence_no: c.sequence_no,
  })),
})

const isStoreCustom = computed(() => form.bom_type === 'STORE_CUSTOM')

function addRow() {
  form.components.push({ component_sku_id: '', consume_qty: 1, loss_rate: 0, sequence_no: form.components.length })
}
function removeRow(idx: number) {
  form.components.splice(idx, 1)
}

function submit() {
  if (form.bom_type === 'STANDARD') form.store_id = null
  router.patch('/tenant/boms/' + props.bom.id, form as unknown as Record<string, FormDataConvertible>)
}
</script>

<template>
  <div class="p-6 max-w-4xl">
    <h1 class="text-2xl font-bold mb-4">编辑配方</h1>
    <!-- 表单结构与 Create.vue 相同（见上一步），不复制注释 -->
    <ElForm :model="form" label-width="120px">
      <ElFormItem label="产出 SKU">
        <ElSelect v-model="form.output_sku_id" filterable>
          <ElOption v-for="sku in outputSkus" :key="sku.id" :label="sku.item.name + ' / ' + sku.spec_name" :value="sku.id" />
        </ElSelect>
      </ElFormItem>
      <ElFormItem label="产出数量">
        <ElInputNumber v-model="form.output_qty" :min="0.0001" :precision="4" />
      </ElFormItem>
      <ElFormItem label="配方类型">
        <ElRadioGroup v-model="form.bom_type">
          <ElRadio value="STANDARD">租户公共</ElRadio>
          <ElRadio value="STORE_CUSTOM">门店私有</ElRadio>
        </ElRadioGroup>
      </ElFormItem>
      <ElFormItem v-if="isStoreCustom" label="所属门店">
        <ElSelect v-model="form.store_id">
          <ElOption v-for="s in stores" :key="s.id" :label="s.name" :value="s.id" />
        </ElSelect>
      </ElFormItem>
      <ElFormItem label="状态">
        <ElRadioGroup v-model="form.status">
          <ElRadio value="active">启用</ElRadio>
          <ElRadio value="disabled">停用</ElRadio>
        </ElRadioGroup>
      </ElFormItem>

      <h2 class="text-lg font-semibold mt-6 mb-2">组件清单</h2>
      <table class="w-full border">
        <thead>
          <tr class="bg-gray-50">
            <th class="p-2 border">组件 SKU</th>
            <th class="p-2 border w-32">单份用量</th>
            <th class="p-2 border w-32">损耗率</th>
            <th class="p-2 border w-24">顺序</th>
            <th class="p-2 border w-20">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, idx) in form.components" :key="idx">
            <td class="p-2 border">
              <ElSelect v-model="row.component_sku_id" filterable>
                <ElOption v-for="sku in componentSkus" :key="sku.id" :label="sku.item.name + ' / ' + sku.spec_name" :value="sku.id" />
              </ElSelect>
            </td>
            <td class="p-2 border"><ElInputNumber v-model="row.consume_qty" :min="0.0001" :precision="4" /></td>
            <td class="p-2 border"><ElInputNumber v-model="row.loss_rate" :min="0" :max="1" :step="0.01" :precision="4" /></td>
            <td class="p-2 border"><ElInputNumber v-model="row.sequence_no" :min="0" :step="1" /></td>
            <td class="p-2 border text-center"><ElButton size="small" type="danger" @click="removeRow(idx)">删除</ElButton></td>
          </tr>
        </tbody>
      </table>
      <ElButton class="mt-2" @click="addRow">+ 添加组件</ElButton>

      <div class="mt-6">
        <ElButton type="primary" @click="submit">保存</ElButton>
      </div>
    </ElForm>
  </div>
</template>
```

- [ ] **Step 2.4.4: vue-tsc 校验**

```bash
npx vue-tsc --noEmit 2>&1 | tail -5
```

期待：0 error。

- [ ] **Step 2.4.5: 跑 Bom 全部测试 + 全量回归**

```bash
./vendor/bin/pest --filter='Bom' 2>&1 | tail -10
./vendor/bin/pest 2>&1 | tail -5
```

期待：BomSchema (5) + BomComponentSchema (4) + TenantBomWebTest (12) 全 PASS；总数从 294 → 315 全绿。

- [ ] **Step 2.6: Commit**

```bash
git add app/Modules/Inventory/Http/Controllers/Web/TenantBomController.php \
        app/Modules/Inventory/Tests/Feature/BomSchemaTest.php \
        app/Modules/Inventory/Tests/Feature/BomComponentSchemaTest.php \
        app/Modules/Inventory/Tests/Feature/TenantBomWebTest.php \
        resources/js/Pages/tenant/Bom/Index.vue \
        resources/js/Pages/tenant/Bom/Create.vue \
        resources/js/Pages/tenant/Bom/Edit.vue \
        routes/web.php
git commit -m "feat(inventory): TenantBomController + Bom CRUD pages"
```

---

## Task 3: ProduceAction（核心算法）

**Files:**
- Create: `app/Modules/Inventory/Actions/ProduceAction.php`
- Create: `app/Modules/Inventory/Tests/Feature/ProduceActionTest.php`

### 3.1 写 ProduceAction

- [ ] **Step 3.1.1: 完整实现 ProduceAction.php**

`app/Modules/Inventory/Actions/ProduceAction.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Catalog\Models\ProductInventoryPolicy;
use App\Modules\Inventory\Events\StockChanged;
use App\Modules\Inventory\Models\Bom;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Inventory\Models\StockTxn;
use App\Modules\Inventory\Models\TenantInventoryConfig;
use App\Modules\Inventory\Support\InventoryGuard;
use App\Support\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;

/**
 * 生产入库（按 BOM 一次产出 N 批）。
 *
 * 流程：
 *   1. 加载 BOM（with components）
 *   2. 校验 store 与 bom 类型一致 + 至少一条 component
 *   3. InventoryGuard 5 层 toggle：output sku 和每个 component sku 都过门
 *   4. 解析 owner + 默认 location（DEFAULT）
 *   5. 计算消耗计划（含 loss_rate × batch_qty）
 *   6. 按 sku_id 字典序锁所有 component balance + output balance
 *   7. 校验余额（双层 negative_stock AND）
 *   8. 写 N 条 PRODUCTION_CONSUME + 更新 component balance
 *   9. 写 1 条 PRODUCTION_OUTPUT + 更新 output balance
 *  10. 提交事务后逐 sku emit StockChanged
 */
class ProduceAction
{
    /**
     * @param  string  $batchQty  按 BOM output_qty 的倍数（bcmath 字符串）
     * @param  array<string, mixed>  $extraMeta  附加 meta_json
     * @return array{consume_txn_ids: list<int>, output_txn_id: int}
     */
    public static function handle(
        string $tenantId,
        string $storeId,
        string $bomId,
        string $batchQty,
        string $operatorId,
        ?string $sourceLocationId = null,
        ?string $outputLocationId = null,
        array $extraMeta = [],
    ): array {
        if (bccomp($batchQty, '0', 4) <= 0) {
            throw new BusinessException('INVALID_BATCH_QTY', "batchQty 必须 > 0，实际：{$batchQty}", 422);
        }

        $bom = Bom::query()->withoutGlobalScopes()
            ->with(['components.componentSku.item'])
            ->where('tenant_id', $tenantId)
            ->where('id', $bomId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();

        if (! $bom) {
            throw new BusinessException('BOM_NOT_FOUND', '配方不存在或已停用', 404);
        }
        if ($bom->bom_type->value === 'STORE_CUSTOM' && $bom->store_id !== $storeId) {
            throw new BusinessException('BOM_STORE_MISMATCH', '门店与配方不匹配', 403);
        }
        if ($bom->components->isEmpty()) {
            throw new BusinessException('BOM_NO_COMPONENTS', '配方无组件', 422);
        }

        // Toggle gate
        InventoryGuard::assertEnabled($tenantId, $storeId, $bom->output_sku_id);
        foreach ($bom->components as $c) {
            InventoryGuard::assertEnabled($tenantId, $storeId, $c->component_sku_id);
        }

        return DB::transaction(function () use (
            $tenantId, $storeId, $bom, $batchQty, $operatorId,
            $sourceLocationId, $outputLocationId, $extraMeta
        ) {
            $owner = StockOwner::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('owner_type', 'STORE')
                ->where('owner_ref_id', $storeId)
                ->firstOrFail();

            $defaultLoc = StockLocation::query()->withoutGlobalScopes()
                ->where('stock_owner_id', $owner->id)
                ->where('location_code', 'DEFAULT')
                ->firstOrFail();

            $sourceLocId = $sourceLocationId ?? $defaultLoc->id;
            $outputLocId = $outputLocationId ?? $defaultLoc->id;

            $totalOutputQty = bcmul((string) $bom->output_qty, $batchQty, 4);

            // 消耗计划
            $consumePlans = [];
            foreach ($bom->components as $c) {
                $consumePlans[] = [
                    'sku_id' => $c->component_sku_id,
                    'qty' => bcmul(
                        bcmul((string) $c->consume_qty, bcadd('1', (string) $c->loss_rate, 4), 4),
                        $batchQty, 4
                    ),
                ];
            }
            // 按 sku_id 字典序锁 → 防死锁
            usort($consumePlans, fn ($a, $b) => strcmp($a['sku_id'], $b['sku_id']));

            // 锁 component balances（不存在视为 null）
            $balances = [];
            foreach ($consumePlans as $plan) {
                $balances[$plan['sku_id']] = StockBalance::query()->withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('stock_owner_id', $owner->id)
                    ->where('location_id', $sourceLocId)
                    ->where('sku_id', $plan['sku_id'])
                    ->lockForUpdate()
                    ->first();
            }

            // 锁 / 创建 output balance
            $outputBalance = StockBalance::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('stock_owner_id', $owner->id)
                ->where('location_id', $outputLocId)
                ->where('sku_id', $bom->output_sku_id)
                ->lockForUpdate()
                ->first();
            if (! $outputBalance) {
                $outputBalance = StockBalance::query()->withoutGlobalScopes()->create([
                    'tenant_id' => $tenantId,
                    'stock_owner_id' => $owner->id,
                    'location_id' => $outputLocId,
                    'sku_id' => $bom->output_sku_id,
                ]);
                $outputBalance = StockBalance::query()->withoutGlobalScopes()
                    ->whereKey($outputBalance->id)->lockForUpdate()->first();
            }

            // 校验余额（双层 AND）
            $tenantCfg = TenantInventoryConfig::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)->first();
            foreach ($consumePlans as $plan) {
                $available = $balances[$plan['sku_id']]?->available_qty ?? '0';
                $remaining = bcsub((string) $available, $plan['qty'], 4);
                if (bccomp($remaining, '0', 4) < 0) {
                    $policy = ProductInventoryPolicy::query()->withoutGlobalScopes()
                        ->where('item_sku_id', $plan['sku_id'])->first();
                    $tenantOk = $tenantCfg && $tenantCfg->negative_stock_allowed;
                    $policyOk = $policy && $policy->allow_negative_stock;
                    if (! ($tenantOk && $policyOk)) {
                        throw new BusinessException('INSUFFICIENT_STOCK',
                            "原料 {$plan['sku_id']} 库存不足", 422);
                    }
                }
            }

            $occurredAt = now();
            $consumeTxnIds = [];

            // 写 PRODUCTION_CONSUME × N
            foreach ($consumePlans as $plan) {
                $negQty = bcsub('0', $plan['qty'], 4);
                $txn = StockTxn::query()->create([
                    'tenant_id' => $tenantId,
                    'biz_type' => 'PRODUCTION_CONSUME',
                    'biz_order_type' => 'BOM',
                    'biz_order_id' => $bom->id,
                    'stock_owner_id' => $owner->id,
                    'location_id' => $sourceLocId,
                    'sku_id' => $plan['sku_id'],
                    'qty_change' => $negQty,
                    'direction' => 'OUT',
                    'occurred_at' => $occurredAt,
                    'operator_id' => $operatorId,
                    'meta_json' => array_merge($extraMeta, [
                        'bom_id' => $bom->id,
                        'batch_qty' => $batchQty,
                        'output_sku_id' => $bom->output_sku_id,
                    ]),
                ]);
                $consumeTxnIds[] = (int) $txn->id;

                if ($balances[$plan['sku_id']]) {
                    $balance = $balances[$plan['sku_id']];
                    $balance->available_qty = bcsub((string) $balance->available_qty, $plan['qty'], 4);
                    $balance->version += 1;
                    $balance->save();
                } else {
                    StockBalance::query()->withoutGlobalScopes()->create([
                        'tenant_id' => $tenantId,
                        'stock_owner_id' => $owner->id,
                        'location_id' => $sourceLocId,
                        'sku_id' => $plan['sku_id'],
                        'available_qty' => $negQty,
                        'version' => 1,
                    ]);
                }
            }

            // 写 PRODUCTION_OUTPUT
            $outputTxn = StockTxn::query()->create([
                'tenant_id' => $tenantId,
                'biz_type' => 'PRODUCTION_OUTPUT',
                'biz_order_type' => 'BOM',
                'biz_order_id' => $bom->id,
                'stock_owner_id' => $owner->id,
                'location_id' => $outputLocId,
                'sku_id' => $bom->output_sku_id,
                'qty_change' => $totalOutputQty,
                'direction' => 'IN',
                'occurred_at' => $occurredAt,
                'operator_id' => $operatorId,
                'meta_json' => array_merge($extraMeta, [
                    'bom_id' => $bom->id,
                    'batch_qty' => $batchQty,
                    'consume_txn_ids' => $consumeTxnIds,
                ]),
            ]);
            $outputBalance->available_qty = bcadd((string) $outputBalance->available_qty, $totalOutputQty, 4);
            $outputBalance->version += 1;
            $outputBalance->save();

            // 事务后再 emit（事务 commit 完才广播）
            DB::afterCommit(function () use (
                $tenantId, $owner, $sourceLocId, $outputLocId,
                $bom, $consumePlans, $consumeTxnIds, $outputTxn
            ) {
                StockChanged::dispatch(
                    $tenantId, $owner->id, $outputLocId, $bom->output_sku_id,
                    (int) $outputTxn->id, 'PRODUCTION_OUTPUT'
                );
                foreach ($consumePlans as $i => $plan) {
                    StockChanged::dispatch(
                        $tenantId, $owner->id, $sourceLocId, $plan['sku_id'],
                        $consumeTxnIds[$i], 'PRODUCTION_CONSUME'
                    );
                }
            });

            return [
                'consume_txn_ids' => $consumeTxnIds,
                'output_txn_id' => (int) $outputTxn->id,
            ];
        });
    }
}
```

### 3.2 测试

- [ ] **Step 3.2.1: 写 ProduceActionTest**

`app/Modules/Inventory/Tests/Feature/ProduceActionTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Catalog\Models\ProductInventoryPolicy;
use App\Modules\Inventory\Actions\ProduceAction;
use App\Modules\Inventory\Events\StockChanged;
use App\Modules\Inventory\Models\Bom;
use App\Modules\Inventory\Models\BomComponent;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Inventory\Models\StockTxn;
use App\Modules\Inventory\Models\TenantInventoryConfig;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Exceptions\BusinessException;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * 搭建生产场景：tenant + store + owner + DEFAULT location +
 * BOM (output_sku, components 数组每项含 [sku, consume_qty, loss_rate])。
 *
 * 注意：StoreInventoryConfigObserver 在 Store 创建时自动建 owner + DEFAULT location。
 *
 * @param  array<int, array{sku: ItemSku, consume: float|string, loss?: float|string}>  $components
 */
function setupProduceScene(
    Tenant $tenant,
    Store $store,
    ItemSku $output,
    string $outputQty,
    array $components,
    string $bomType = 'STANDARD',
    ?string $storeIdOnBom = null,
): array {
    CurrentTenant::set($tenant->id);

    // 确保产出和组件 sku 的 policy.inventory_track_type != NONE，否则 InventoryGuard 拒绝
    foreach (array_merge([['sku' => $output]], $components) as $row) {
        ProductInventoryPolicy::query()->withoutGlobalScopes()
            ->updateOrCreate(
                ['item_sku_id' => $row['sku']->id],
                [
                    'tenant_id' => $tenant->id,
                    'inventory_track_type' => 'BY_SKU',
                    'stock_deduct_mode' => 'MANUAL_DEDUCT',
                    'allow_negative_stock' => false,
                ],
            );
    }

    $bom = Bom::factory()->create([
        'tenant_id' => $tenant->id,
        'output_sku_id' => $output->id,
        'output_qty' => $outputQty,
        'bom_type' => $bomType,
        'store_id' => $storeIdOnBom,
    ]);
    foreach ($components as $i => $row) {
        BomComponent::factory()->create([
            'bom_id' => $bom->id,
            'component_sku_id' => $row['sku']->id,
            'consume_qty' => (string) $row['consume'],
            'loss_rate' => (string) ($row['loss'] ?? '0'),
            'sequence_no' => $i,
        ]);
    }

    $owner = StockOwner::query()->withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('owner_type', 'STORE')->where('owner_ref_id', $store->id)
        ->firstOrFail();
    $location = StockLocation::query()->withoutGlobalScopes()
        ->where('stock_owner_id', $owner->id)->where('location_code', 'DEFAULT')
        ->firstOrFail();

    return [$bom, $owner, $location];
}

function makeProduceSku(string $tenantId, string $itemType): ItemSku
{
    $cat = Category::factory()->create([
        'tenant_id' => $tenantId, 'owner_type' => 'TENANT', 'owner_store_id' => null,
    ]);
    $item = Item::factory()->create([
        'tenant_id' => $tenantId,
        'item_type' => $itemType,
        'business_category_id' => $cat->id,
    ]);
    return ItemSku::factory()->create([
        'tenant_id' => $tenantId,
        'item_id' => $item->id,
    ]);
}

function preloadBalance(string $tenantId, string $ownerId, string $locId, string $skuId, string $qty): void
{
    StockBalance::query()->withoutGlobalScopes()->create([
        'tenant_id' => $tenantId,
        'stock_owner_id' => $ownerId,
        'location_id' => $locId,
        'sku_id' => $skuId,
        'available_qty' => $qty,
    ]);
}

test('正常路径：1 component, loss=0.1, batch=5 → consume -55, output +5', function () {
    $tenant = Tenant::factory()->create();
    $store = Store::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();

    $output = makeProduceSku($tenant->id, 'SALE_PRODUCT');
    $rawA = makeProduceSku($tenant->id, 'RAW_MATERIAL');

    [$bom, $owner, $loc] = setupProduceScene($tenant, $store, $output, '1', [
        ['sku' => $rawA, 'consume' => '10', 'loss' => '0.1'],
    ]);
    preloadBalance($tenant->id, $owner->id, $loc->id, $rawA->id, '100');

    $result = ProduceAction::handle(
        $tenant->id, $store->id, $bom->id, '5', $user->id
    );

    expect($result['consume_txn_ids'])->toHaveCount(1);
    expect($result['output_txn_id'])->toBeInt();

    $consume = StockTxn::query()->find($result['consume_txn_ids'][0]);
    expect((string) $consume->qty_change)->toBe('-55.0000');
    expect($consume->biz_type->value)->toBe('PRODUCTION_CONSUME');

    $outputTxn = StockTxn::query()->find($result['output_txn_id']);
    expect((string) $outputTxn->qty_change)->toBe('5.0000');
    expect($outputTxn->biz_type->value)->toBe('PRODUCTION_OUTPUT');

    $rawBal = StockBalance::query()->withoutGlobalScopes()
        ->where('sku_id', $rawA->id)->first();
    expect((string) $rawBal->available_qty)->toBe('45.0000');

    $outBal = StockBalance::query()->withoutGlobalScopes()
        ->where('sku_id', $output->id)->first();
    expect((string) $outBal->available_qty)->toBe('5.0000');
});

test('output_qty=0.5 BOM × batch=4 = 2 杯 output', function () {
    $tenant = Tenant::factory()->create();
    $store = Store::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();
    $output = makeProduceSku($tenant->id, 'SALE_PRODUCT');
    $raw = makeProduceSku($tenant->id, 'RAW_MATERIAL');

    [$bom, $owner, $loc] = setupProduceScene($tenant, $store, $output, '0.5', [
        ['sku' => $raw, 'consume' => '2', 'loss' => '0'],
    ]);
    preloadBalance($tenant->id, $owner->id, $loc->id, $raw->id, '100');

    $result = ProduceAction::handle($tenant->id, $store->id, $bom->id, '4', $user->id);

    $outBal = StockBalance::query()->withoutGlobalScopes()->where('sku_id', $output->id)->first();
    expect((string) $outBal->available_qty)->toBe('2.0000');
});

test('component balance 不存在 + 不允许负库存 → INSUFFICIENT_STOCK 抛出且无写入', function () {
    $tenant = Tenant::factory()->create();
    $store = Store::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();
    $output = makeProduceSku($tenant->id, 'SALE_PRODUCT');
    $raw = makeProduceSku($tenant->id, 'RAW_MATERIAL');

    [$bom] = setupProduceScene($tenant, $store, $output, '1', [
        ['sku' => $raw, 'consume' => '10', 'loss' => '0'],
    ]);

    expect(fn () => ProduceAction::handle($tenant->id, $store->id, $bom->id, '1', $user->id))
        ->toThrow(BusinessException::class, '库存不足');

    expect(StockTxn::query()->count())->toBe(0);
});

test('component balance=0 + 允许负库存 → 创建一行负 balance', function () {
    $tenant = Tenant::factory()->create();
    $store = Store::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();

    // 启用 tenant + policy 双允许负库存
    TenantInventoryConfig::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)
        ->update(['negative_stock_allowed' => true]);

    $output = makeProduceSku($tenant->id, 'SALE_PRODUCT');
    $raw = makeProduceSku($tenant->id, 'RAW_MATERIAL');

    [$bom, $owner, $loc] = setupProduceScene($tenant, $store, $output, '1', [
        ['sku' => $raw, 'consume' => '5', 'loss' => '0'],
    ]);
    ProductInventoryPolicy::query()->withoutGlobalScopes()
        ->where('item_sku_id', $raw->id)->update(['allow_negative_stock' => true]);

    ProduceAction::handle($tenant->id, $store->id, $bom->id, '2', $user->id);

    $rawBal = StockBalance::query()->withoutGlobalScopes()->where('sku_id', $raw->id)->first();
    expect((string) $rawBal->available_qty)->toBe('-10.0000');
});

test('跨租户 BOM → BOM_NOT_FOUND', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $store = Store::factory()->create(['tenant_id' => $tenantB->id]);
    $user = User::factory()->create();

    CurrentTenant::set($tenantA->id);
    $output = makeProduceSku($tenantA->id, 'SALE_PRODUCT');
    $raw = makeProduceSku($tenantA->id, 'RAW_MATERIAL');
    [$bom] = setupProduceScene($tenantA, Store::factory()->create(['tenant_id' => $tenantA->id]),
        $output, '1', [['sku' => $raw, 'consume' => '1', 'loss' => '0']]);

    expect(fn () => ProduceAction::handle($tenantB->id, $store->id, $bom->id, '1', $user->id))
        ->toThrow(BusinessException::class, '配方不存在');
});

test('STORE_CUSTOM bom 与传入 store_id 不一致 → BOM_STORE_MISMATCH', function () {
    $tenant = Tenant::factory()->create();
    $storeA = Store::factory()->create(['tenant_id' => $tenant->id]);
    $storeB = Store::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();

    $output = makeProduceSku($tenant->id, 'SALE_PRODUCT');
    $raw = makeProduceSku($tenant->id, 'RAW_MATERIAL');

    [$bom] = setupProduceScene($tenant, $storeA, $output, '1',
        [['sku' => $raw, 'consume' => '1', 'loss' => '0']],
        'STORE_CUSTOM', $storeA->id);

    expect(fn () => ProduceAction::handle($tenant->id, $storeB->id, $bom->id, '1', $user->id))
        ->toThrow(BusinessException::class, '门店与配方不匹配');
});

test('output_sku toggle 关闭 → InventoryDisabledException', function () {
    $tenant = Tenant::factory()->create();
    $store = Store::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();

    $output = makeProduceSku($tenant->id, 'SALE_PRODUCT');
    $raw = makeProduceSku($tenant->id, 'RAW_MATERIAL');

    [$bom] = setupProduceScene($tenant, $store, $output, '1',
        [['sku' => $raw, 'consume' => '1', 'loss' => '0']]);

    // 把 output 的 policy.inventory_track_type 改为 NONE
    ProductInventoryPolicy::query()->withoutGlobalScopes()
        ->where('item_sku_id', $output->id)
        ->update(['inventory_track_type' => 'NONE']);

    expect(fn () => ProduceAction::handle($tenant->id, $store->id, $bom->id, '1', $user->id))
        ->toThrow(\App\Modules\Inventory\Exceptions\InventoryDisabledException::class);
});

test('component sku toggle 关闭 → InventoryDisabledException', function () {
    $tenant = Tenant::factory()->create();
    $store = Store::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();

    $output = makeProduceSku($tenant->id, 'SALE_PRODUCT');
    $raw = makeProduceSku($tenant->id, 'RAW_MATERIAL');

    [$bom] = setupProduceScene($tenant, $store, $output, '1',
        [['sku' => $raw, 'consume' => '1', 'loss' => '0']]);

    ProductInventoryPolicy::query()->withoutGlobalScopes()
        ->where('item_sku_id', $raw->id)
        ->update(['inventory_track_type' => 'NONE']);

    expect(fn () => ProduceAction::handle($tenant->id, $store->id, $bom->id, '1', $user->id))
        ->toThrow(\App\Modules\Inventory\Exceptions\InventoryDisabledException::class);
});

test('bom 无 component → BOM_NO_COMPONENTS', function () {
    $tenant = Tenant::factory()->create();
    $store = Store::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();
    CurrentTenant::set($tenant->id);

    $output = makeProduceSku($tenant->id, 'SALE_PRODUCT');
    ProductInventoryPolicy::query()->withoutGlobalScopes()->updateOrCreate(
        ['item_sku_id' => $output->id],
        ['tenant_id' => $tenant->id, 'inventory_track_type' => 'BY_SKU',
         'stock_deduct_mode' => 'MANUAL_DEDUCT', 'allow_negative_stock' => false],
    );
    $bom = Bom::factory()->create([
        'tenant_id' => $tenant->id, 'output_sku_id' => $output->id,
    ]);

    expect(fn () => ProduceAction::handle($tenant->id, $store->id, $bom->id, '1', $user->id))
        ->toThrow(BusinessException::class, '配方无组件');
});

test('bom status=disabled → BOM_NOT_FOUND', function () {
    $tenant = Tenant::factory()->create();
    $store = Store::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();

    $output = makeProduceSku($tenant->id, 'SALE_PRODUCT');
    $raw = makeProduceSku($tenant->id, 'RAW_MATERIAL');
    [$bom] = setupProduceScene($tenant, $store, $output, '1',
        [['sku' => $raw, 'consume' => '1', 'loss' => '0']]);
    $bom->update(['status' => 'disabled']);

    expect(fn () => ProduceAction::handle($tenant->id, $store->id, $bom->id, '1', $user->id))
        ->toThrow(BusinessException::class, '配方不存在');
});

test('bom 软删 → BOM_NOT_FOUND', function () {
    $tenant = Tenant::factory()->create();
    $store = Store::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();

    $output = makeProduceSku($tenant->id, 'SALE_PRODUCT');
    $raw = makeProduceSku($tenant->id, 'RAW_MATERIAL');
    [$bom] = setupProduceScene($tenant, $store, $output, '1',
        [['sku' => $raw, 'consume' => '1', 'loss' => '0']]);
    $bom->delete();

    expect(fn () => ProduceAction::handle($tenant->id, $store->id, $bom->id, '1', $user->id))
        ->toThrow(BusinessException::class, '配方不存在');
});

test('source_location_id 与 output_location_id 不同 → 两条 txn 落对应 location', function () {
    $tenant = Tenant::factory()->create();
    $store = Store::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();

    $output = makeProduceSku($tenant->id, 'SALE_PRODUCT');
    $raw = makeProduceSku($tenant->id, 'RAW_MATERIAL');
    [$bom, $owner, $defaultLoc] = setupProduceScene($tenant, $store, $output, '1',
        [['sku' => $raw, 'consume' => '5', 'loss' => '0']]);

    // 额外建一个 location 当成品入库位
    $altLoc = StockLocation::query()->withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'stock_owner_id' => $owner->id,
        'location_code' => 'KITCHEN',
        'location_name' => '后厨',
        'location_type' => 'BACKROOM',
    ]);

    preloadBalance($tenant->id, $owner->id, $defaultLoc->id, $raw->id, '100');

    $result = ProduceAction::handle(
        $tenant->id, $store->id, $bom->id, '1', $user->id,
        $defaultLoc->id, $altLoc->id
    );

    $consumeTxn = StockTxn::query()->find($result['consume_txn_ids'][0]);
    $outputTxn = StockTxn::query()->find($result['output_txn_id']);
    expect($consumeTxn->location_id)->toBe($defaultLoc->id);
    expect($outputTxn->location_id)->toBe($altLoc->id);
});

test('成功提交后 emit StockChanged 事件 N+1 次', function () {
    Event::fake();

    $tenant = Tenant::factory()->create();
    $store = Store::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();

    $output = makeProduceSku($tenant->id, 'SALE_PRODUCT');
    $rawA = makeProduceSku($tenant->id, 'RAW_MATERIAL');
    $rawB = makeProduceSku($tenant->id, 'RAW_MATERIAL');

    [$bom, $owner, $loc] = setupProduceScene($tenant, $store, $output, '1', [
        ['sku' => $rawA, 'consume' => '5', 'loss' => '0'],
        ['sku' => $rawB, 'consume' => '3', 'loss' => '0'],
    ]);
    preloadBalance($tenant->id, $owner->id, $loc->id, $rawA->id, '100');
    preloadBalance($tenant->id, $owner->id, $loc->id, $rawB->id, '100');

    ProduceAction::handle($tenant->id, $store->id, $bom->id, '1', $user->id);

    Event::assertDispatchedTimes(StockChanged::class, 3);  // 1 output + 2 components
});

test('batchQty 无效（<=0）→ INVALID_BATCH_QTY', function () {
    $tenant = Tenant::factory()->create();
    $store = Store::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();
    CurrentTenant::set($tenant->id);
    $bom = Bom::factory()->create(['tenant_id' => $tenant->id]);

    expect(fn () => ProduceAction::handle($tenant->id, $store->id, $bom->id, '0', $user->id))
        ->toThrow(BusinessException::class, 'batchQty');
});
```

- [ ] **Step 3.2.2: 跑 ProduceActionTest**

```bash
./vendor/bin/pest --filter='ProduceAction' 2>&1 | tail -20
```

期待：13 个用例全 PASS。

调试预期点：
- 如果 `negative_stock_allowed` 字段名不对：确认 `tenant_inventory_configs` 字段名（grep migration）
- 如果 `ProductInventoryPolicy` 主键不是 `item_sku_id`：参照 `app/Modules/Catalog/Models/ProductInventoryPolicy.php` 查实际字段
- 如果 InventoryDisabledException 类不存在：参照已有 InventoryGuard 的 throw 路径

- [ ] **Step 3.3: 全量回归**

```bash
./vendor/bin/pest 2>&1 | tail -5
```

期待：315 + 13 = 328 全绿。

- [ ] **Step 3.4: Commit**

```bash
git add app/Modules/Inventory/Actions/ProduceAction.php \
        app/Modules/Inventory/Tests/Feature/ProduceActionTest.php
git commit -m "feat(inventory): ProduceAction with loss + lockForUpdate + N+1 txns"
```

---

## Task 4: TenantProduceController + Produce/Index Vue + 预览端点

**Files:**
- Create: `app/Modules/Inventory/Http/Controllers/Web/TenantProduceController.php`
- Create: `resources/js/Pages/tenant/Produce/Index.vue`
- Modify: `routes/web.php`（追加 3 条 produce 路由）
- Create: `app/Modules/Inventory/Tests/Feature/TenantProduceWebTest.php`

### 4.1 控制器

- [ ] **Step 4.1.1: 写 TenantProduceController**

`app/Modules/Inventory/Http/Controllers/Web/TenantProduceController.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers\Web;

use App\Modules\Catalog\Models\ProductInventoryPolicy;
use App\Modules\Inventory\Actions\ProduceAction;
use App\Modules\Inventory\Models\Bom;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Inventory\Models\TenantInventoryConfig;
use App\Modules\Tenancy\Models\Store;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TenantProduceController extends Controller
{
    public function create(Request $request): Response
    {
        $tenantId = $this->requireCurrentTenant($request);

        return Inertia::render('tenant/Produce/Index', [
            'stores' => Store::query()->where('tenant_id', $tenantId)->get(['id', 'name']),
            'boms' => Bom::query()->with('outputSku.item')
                ->where('status', 'active')
                ->get(['id', 'output_sku_id', 'output_qty', 'bom_type', 'store_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        $data = $request->validate([
            'store_id' => ['required', 'string', 'size:26',
                Rule::exists('stores', 'id')->where('tenant_id', $tenantId)],
            'bom_id' => ['required', 'string', 'size:26'],
            'batch_qty' => ['required', 'numeric', 'gt:0'],
            'source_location_id' => ['nullable', 'string', 'size:26'],
            'output_location_id' => ['nullable', 'string', 'size:26'],
        ]);

        ProduceAction::handle(
            $tenantId,
            $data['store_id'],
            $data['bom_id'],
            (string) $data['batch_qty'],
            (string) $request->user()->id,
            $data['source_location_id'] ?? null,
            $data['output_location_id'] ?? null,
        );

        return redirect('/tenant/produce')->with('success', '生产入库已完成');
    }

    public function preview(Request $request): JsonResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        $data = $request->validate([
            'store_id' => ['required', 'string', 'size:26'],
            'bom_id' => ['required', 'string', 'size:26'],
            'batch_qty' => ['required', 'numeric', 'gt:0'],
        ]);

        $bom = Bom::query()->withoutGlobalScopes()
            ->with(['outputSku.item', 'components.componentSku.item'])
            ->where('tenant_id', $tenantId)
            ->where('id', $data['bom_id'])
            ->whereNull('deleted_at')
            ->firstOrFail();

        $owner = StockOwner::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('owner_type', 'STORE')->where('owner_ref_id', $data['store_id'])
            ->firstOrFail();
        $defaultLoc = StockLocation::query()->withoutGlobalScopes()
            ->where('stock_owner_id', $owner->id)->where('location_code', 'DEFAULT')
            ->firstOrFail();

        $batchQty = (string) $data['batch_qty'];
        $totalOutput = bcmul((string) $bom->output_qty, $batchQty, 4);

        $consumes = [];
        foreach ($bom->components as $c) {
            $needed = bcmul(
                bcmul((string) $c->consume_qty, bcadd('1', (string) $c->loss_rate, 4), 4),
                $batchQty, 4
            );
            $balance = StockBalance::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('stock_owner_id', $owner->id)
                ->where('location_id', $defaultLoc->id)
                ->where('sku_id', $c->component_sku_id)
                ->first();
            $available = (string) ($balance?->available_qty ?? '0');

            $consumes[] = [
                'sku_id' => $c->component_sku_id,
                'sku_name' => $c->componentSku->item->name . ' / ' . $c->componentSku->spec_name,
                'needed' => $needed,
                'available' => $available,
                'sufficient' => bccomp($available, $needed, 4) >= 0,
            ];
        }

        return response()->json([
            'output' => [
                'sku_id' => $bom->output_sku_id,
                'sku_name' => $bom->outputSku->item->name . ' / ' . $bom->outputSku->spec_name,
                'qty' => $totalOutput,
            ],
            'consumes' => $consumes,
        ]);
    }

    private function requireCurrentTenant(Request $request): string
    {
        $id = CurrentTenant::id();
        abort_if($id === null, 403, '未绑定租户');
        return $id;
    }
}
```

- [ ] **Step 4.1.2: 路由注册**

`routes/web.php` 顶部 imports 区追加：
```php
use App\Modules\Inventory\Http\Controllers\Web\TenantProduceController;
```

在 BOM 路由之后追加：
```php
    Route::get('produce', [TenantProduceController::class, 'create']);
    Route::post('produce', [TenantProduceController::class, 'store']);
    Route::get('produce/preview', [TenantProduceController::class, 'preview']);
```

### 4.2 Vue 页

- [ ] **Step 4.2.1: 写 Produce/Index.vue**

`resources/js/Pages/tenant/Produce/Index.vue`：

```vue
<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core'
import { router } from '@inertiajs/vue3'
import { ElButton, ElCollapse, ElCollapseItem, ElForm, ElFormItem, ElInputNumber, ElMessage, ElOption, ElSelect, ElTable, ElTableColumn, ElTag } from 'element-plus'
import { computed, reactive, ref, watch } from 'vue'

interface StoreOption { id: string; name: string }
interface BomOption {
  id: string; output_sku_id: string; output_qty: string
  bom_type: 'STANDARD' | 'STORE_CUSTOM'; store_id: string | null
  output_sku: { item: { name: string }; spec_name: string }
}
interface PreviewConsume {
  sku_id: string; sku_name: string; needed: string; available: string; sufficient: boolean
}
interface Preview {
  output: { sku_id: string; sku_name: string; qty: string }
  consumes: PreviewConsume[]
}

interface Props {
  stores: StoreOption[]
  boms: BomOption[]
}
const props = defineProps<Props>()

const form = reactive({
  store_id: '',
  bom_id: '',
  batch_qty: 1,
  source_location_id: null as string | null,
  output_location_id: null as string | null,
})

const eligibleBoms = computed(() =>
  props.boms.filter(b =>
    b.bom_type === 'STANDARD' ||
    (b.bom_type === 'STORE_CUSTOM' && b.store_id === form.store_id)
  )
)

const preview = ref<Preview | null>(null)
const allSufficient = computed(() =>
  preview.value?.consumes.every(c => c.sufficient) ?? false
)

async function fetchPreview() {
  if (!form.store_id || !form.bom_id || form.batch_qty <= 0) {
    preview.value = null
    return
  }
  const params = new URLSearchParams({
    store_id: form.store_id, bom_id: form.bom_id, batch_qty: String(form.batch_qty),
  })
  const resp = await fetch('/tenant/produce/preview?' + params.toString(), {
    headers: { Accept: 'application/json' },
  })
  if (resp.ok) preview.value = await resp.json()
}

watch(() => [form.store_id, form.bom_id, form.batch_qty], fetchPreview, { immediate: false })

function submit() {
  if (!allSufficient.value) {
    ElMessage.warning('原料库存不足，无法生产')
    return
  }
  router.post('/tenant/produce', form as unknown as Record<string, FormDataConvertible>)
}
</script>

<template>
  <div class="p-6 max-w-5xl">
    <h1 class="text-2xl font-bold mb-4">生产入库</h1>

    <ElForm :model="form" label-width="120px">
      <ElFormItem label="门店">
        <ElSelect v-model="form.store_id">
          <ElOption v-for="s in stores" :key="s.id" :label="s.name" :value="s.id" />
        </ElSelect>
      </ElFormItem>

      <ElFormItem label="配方">
        <ElSelect v-model="form.bom_id" filterable>
          <ElOption
            v-for="b in eligibleBoms"
            :key="b.id"
            :label="b.output_sku.item.name + ' / ' + b.output_sku.spec_name + ' (×' + b.output_qty + ')'"
            :value="b.id"
          />
        </ElSelect>
      </ElFormItem>

      <ElFormItem label="生产批次数">
        <ElInputNumber v-model="form.batch_qty" :min="0.0001" :precision="4" />
      </ElFormItem>

      <ElCollapse>
        <ElCollapseItem title="高级：自定义库位（默认 DEFAULT）">
          <ElFormItem label="原料出库位 ID">
            <input v-model="form.source_location_id" class="border p-1" placeholder="留空 = DEFAULT" />
          </ElFormItem>
          <ElFormItem label="成品入库位 ID">
            <input v-model="form.output_location_id" class="border p-1" placeholder="留空 = DEFAULT" />
          </ElFormItem>
        </ElCollapseItem>
      </ElCollapse>
    </ElForm>

    <div v-if="preview" class="mt-6">
      <h2 class="text-lg font-semibold mb-2">预览</h2>
      <p class="mb-2">
        将产出：<strong>{{ preview.output.sku_name }}</strong> × {{ preview.output.qty }}
      </p>
      <ElTable :data="preview.consumes" border>
        <ElTableColumn label="原料" prop="sku_name" min-width="240" />
        <ElTableColumn label="实际消耗（含损耗）" prop="needed" width="160" />
        <ElTableColumn label="当前库存" prop="available" width="140" />
        <ElTableColumn label="状态" width="100">
          <template #default="{ row }">
            <ElTag v-if="row.sufficient" type="success">充足</ElTag>
            <ElTag v-else type="danger">不足</ElTag>
          </template>
        </ElTableColumn>
      </ElTable>
    </div>

    <div class="mt-6">
      <ElButton type="primary" :disabled="!preview || !allSufficient" @click="submit">提交生产</ElButton>
    </div>
  </div>
</template>
```

### 4.3 Web 测试

- [ ] **Step 4.3.1: 写 TenantProduceWebTest**

`app/Modules/Inventory/Tests/Feature/TenantProduceWebTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\Membership;
use App\Modules\Authorization\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Catalog\Models\ProductInventoryPolicy;
use App\Modules\Inventory\Models\Bom;
use App\Modules\Inventory\Models\BomComponent;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Inventory\Models\StockTxn;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeProduceWebSku(string $tenantId, string $itemType): ItemSku
{
    $cat = Category::factory()->create([
        'tenant_id' => $tenantId, 'owner_type' => 'TENANT', 'owner_store_id' => null,
    ]);
    $item = Item::factory()->create([
        'tenant_id' => $tenantId,
        'item_type' => $itemType,
        'business_category_id' => $cat->id,
    ]);
    $sku = ItemSku::factory()->create(['tenant_id' => $tenantId, 'item_id' => $item->id]);
    ProductInventoryPolicy::query()->withoutGlobalScopes()->updateOrCreate(
        ['item_sku_id' => $sku->id],
        ['tenant_id' => $tenantId, 'inventory_track_type' => 'BY_SKU',
         'stock_deduct_mode' => 'MANUAL_DEDUCT', 'allow_negative_stock' => false],
    );
    return $sku;
}

function loginTenantUser(Tenant $tenant): User
{
    $user = User::factory()->create();
    Membership::factory()->create(['user_id' => $user->id, 'tenant_id' => $tenant->id]);
    test()->actingAs($user);
    CurrentTenant::set($tenant->id);
    return $user;
}

test('GET /tenant/produce 渲染并下发 boms + stores', function () {
    $tenant = Tenant::factory()->create();
    loginTenantUser($tenant);
    Store::factory()->create(['tenant_id' => $tenant->id]);
    $output = makeProduceWebSku($tenant->id, 'SALE_PRODUCT');
    Bom::factory()->create(['tenant_id' => $tenant->id, 'output_sku_id' => $output->id]);

    test()->get('/tenant/produce')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('tenant/Produce/Index')
            ->has('stores', 1)
            ->has('boms', 1));
});

test('POST /tenant/produce 合法 → 写入 stock_txns + flash', function () {
    $tenant = Tenant::factory()->create();
    $store = Store::factory()->create(['tenant_id' => $tenant->id]);
    loginTenantUser($tenant);

    $output = makeProduceWebSku($tenant->id, 'SALE_PRODUCT');
    $raw = makeProduceWebSku($tenant->id, 'RAW_MATERIAL');

    $bom = Bom::factory()->create([
        'tenant_id' => $tenant->id, 'output_sku_id' => $output->id,
        'output_qty' => '1', 'bom_type' => 'STANDARD',
    ]);
    BomComponent::factory()->create([
        'bom_id' => $bom->id, 'component_sku_id' => $raw->id,
        'consume_qty' => '5', 'loss_rate' => '0',
    ]);

    $owner = StockOwner::query()->withoutGlobalScopes()
        ->where('owner_ref_id', $store->id)->firstOrFail();
    $loc = StockLocation::query()->withoutGlobalScopes()
        ->where('stock_owner_id', $owner->id)->where('location_code', 'DEFAULT')->firstOrFail();
    StockBalance::query()->withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id, 'stock_owner_id' => $owner->id,
        'location_id' => $loc->id, 'sku_id' => $raw->id, 'available_qty' => '100',
    ]);

    test()->post('/tenant/produce', [
        'store_id' => $store->id, 'bom_id' => $bom->id, 'batch_qty' => 2,
    ])->assertRedirect('/tenant/produce')->assertSessionHas('success');

    expect(StockTxn::query()->where('biz_type', 'PRODUCTION_OUTPUT')->count())->toBe(1);
    expect(StockTxn::query()->where('biz_type', 'PRODUCTION_CONSUME')->count())->toBe(1);
});

test('GET /tenant/produce/preview 返回 output + consumes 含 sufficient flag', function () {
    $tenant = Tenant::factory()->create();
    $store = Store::factory()->create(['tenant_id' => $tenant->id]);
    loginTenantUser($tenant);

    $output = makeProduceWebSku($tenant->id, 'SALE_PRODUCT');
    $raw = makeProduceWebSku($tenant->id, 'RAW_MATERIAL');

    $bom = Bom::factory()->create(['tenant_id' => $tenant->id, 'output_sku_id' => $output->id, 'output_qty' => '1']);
    BomComponent::factory()->create([
        'bom_id' => $bom->id, 'component_sku_id' => $raw->id,
        'consume_qty' => '5', 'loss_rate' => '0.1',
    ]);

    $resp = test()->getJson('/tenant/produce/preview?store_id=' . $store->id .
        '&bom_id=' . $bom->id . '&batch_qty=2');

    $resp->assertOk();
    $resp->assertJsonPath('output.qty', '2.0000');
    $resp->assertJsonPath('consumes.0.needed', '11.0000');  // 5 × 1.1 × 2
    $resp->assertJsonPath('consumes.0.available', '0');
    $resp->assertJsonPath('consumes.0.sufficient', false);
});

test('GET /tenant/produce/preview 跨租户 bom_id → 404', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $storeB = Store::factory()->create(['tenant_id' => $tenantB->id]);
    loginTenantUser($tenantB);

    CurrentTenant::set($tenantA->id);
    $crossSku = makeProduceWebSku($tenantA->id, 'SALE_PRODUCT');
    $crossBom = Bom::factory()->create(['tenant_id' => $tenantA->id, 'output_sku_id' => $crossSku->id]);

    CurrentTenant::set($tenantB->id);
    test()->getJson('/tenant/produce/preview?store_id=' . $storeB->id .
        '&bom_id=' . $crossBom->id . '&batch_qty=1')->assertNotFound();
});

test('POST /tenant/produce 跨租户 bom_id → 404', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $storeB = Store::factory()->create(['tenant_id' => $tenantB->id]);
    loginTenantUser($tenantB);

    CurrentTenant::set($tenantA->id);
    $crossSku = makeProduceWebSku($tenantA->id, 'SALE_PRODUCT');
    $crossBom = Bom::factory()->create(['tenant_id' => $tenantA->id, 'output_sku_id' => $crossSku->id]);

    CurrentTenant::set($tenantB->id);
    test()->post('/tenant/produce', [
        'store_id' => $storeB->id, 'bom_id' => $crossBom->id, 'batch_qty' => 1,
    ])->assertStatus(404);
});

test('POST /tenant/produce 跨租户 store_id → 422', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $crossStore = Store::factory()->create(['tenant_id' => $tenantA->id]);
    loginTenantUser($tenantB);

    $output = makeProduceWebSku($tenantB->id, 'SALE_PRODUCT');
    $bom = Bom::factory()->create(['tenant_id' => $tenantB->id, 'output_sku_id' => $output->id]);

    test()->post('/tenant/produce', [
        'store_id' => $crossStore->id, 'bom_id' => $bom->id, 'batch_qty' => 1,
    ])->assertSessionHasErrors('store_id');
});
```

- [ ] **Step 4.3.2: 跑测试**

```bash
./vendor/bin/pest --filter='TenantProduceWebTest' 2>&1 | tail -10
```

期待：6 个用例全 PASS。

- [ ] **Step 4.4: vue-tsc + 全量回归**

```bash
npx vue-tsc --noEmit 2>&1 | tail -5
./vendor/bin/pest 2>&1 | tail -5
```

期待：vue-tsc 0 error；全量 328 + 6 = 334 全绿。

- [ ] **Step 4.5: Commit**

```bash
git add app/Modules/Inventory/Http/Controllers/Web/TenantProduceController.php \
        app/Modules/Inventory/Tests/Feature/TenantProduceWebTest.php \
        resources/js/Pages/tenant/Produce/Index.vue \
        routes/web.php
git commit -m "feat(inventory): TenantProduceController + Produce form + preview JSON"
```

---

## Task 5: 侧栏 + i18n + 收尾冒烟

**Files:**
- Modify: `resources/js/composables/useNavigation.ts`
- Modify: `resources/js/lib/locales/zh-CN/nav.ts`

- [ ] **Step 5.1: 更新 useNavigation.ts**

读 `resources/js/composables/useNavigation.ts`，定位到 inventory 模块的 children 数组（包含 stock / settings 等条目）。在 stock 条目**之前**追加 2 项：

```ts
{ name: 'boms',    label: t('nav.inventory.boms'),    route: '/tenant/boms',    permission: 'boms.read' },
{ name: 'produce', label: t('nav.inventory.produce'), route: '/tenant/produce', permission: 'production.execute' },
```

注意路由形式跟同 inventory 模块下其他条目保持一致（应该是路径字符串而非 named route）。如果发现实际是 named route，按实际格式来，**不要造数据**——参照已有 `categories` / `items` / `stock` 三项写法。

- [ ] **Step 5.2: 更新 nav.ts**

打开 `resources/js/lib/locales/zh-CN/nav.ts`，在 inventory 块内追加：

```ts
boms: '配方管理',
produce: '生产入库',
```

- [ ] **Step 5.3: vue-tsc**

```bash
npx vue-tsc --noEmit 2>&1 | tail -5
```

期待：0 error。

- [ ] **Step 5.4: 全量回归**

```bash
./vendor/bin/pest 2>&1 | tail -5
```

期待：334 全绿。

- [ ] **Step 5.5: Commit**

```bash
git add resources/js/composables/useNavigation.ts \
        resources/js/lib/locales/zh-CN/nav.ts
git commit -m "feat(inventory): sidebar + i18n for BOM + Produce"
```

---

## 完成标志

- 5 个 task 全部 commit 完毕
- `./vendor/bin/pest` 全绿（约 334 用例）
- `npx vue-tsc --noEmit` 0 error
- 路由表 `php artisan route:list --uri=tenant/boms` 与 `tenant/produce` 各 6 条 + 3 条
- 侧栏新增「配方管理」「生产入库」入口

最终（如果有 reviewer 反馈）走 Task 6 修 fix 提 commit。

---

## Self-Review

按 writing-plans skill 的 self-review 清单核查（已完成）：

**1. Spec coverage:**
- §1.1 目标 3 项（CRUD / Action / UI）→ T2/T3/T4 覆盖
- §1.2 非目标 7 项 → 在文档与本 plan 顶部"重要执行约定"中声明，不在任何 task 范围
- §2 业务约束 8 条 → T2 Validator 覆盖 1-5，T3 Action 覆盖 6-8
- §3 schema → 已存在，本 plan 不改表
- §4 BOM CRUD → T2
- §5 ProduceAction → T3
- §6 Web 入口 → T4
- §7 路由 → T2/T4 中的 routes/web.php 修改步骤
- §8 权限 → T1
- §9 侧栏 + i18n → T5
- §10 测试矩阵 → T2-T4 各自的测试文件，单元数大致对应（schema 9 + web 12 + action 13 + produce web 6 = 40 + 4 perm = 44，比 spec §10 估计的 50 略少，覆盖关键场景）
- §11 task 边界 → 直接对应 5 task

**2. Placeholder scan:** 已逐 task 复核，无 TBD/TODO/"按上一节"等。

**3. Type consistency:**
- ProduceAction 签名（参数顺序）在 §5、Task 3、Task 4 控制器调用处全部一致
- 字段名（`stock_owner_id` / `location_code` / `available_qty`）在 plan 顶部约定 + 各任务实现中一致
- 角色 code（`TenantAdmin` / `StoreManager` / `StoreClerk`）在 T1 + spec §8 一致
- Permission 枚举值（`boms.read` 等 6 项）在 T1 + spec §8 一致
