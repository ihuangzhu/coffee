# 库存模块第一期设计（严格按 item_stock.md + categories.md）

> 适用对象：本项目（`/mnt/d/Projects/Huang/coffee`）库存模块第一期。
> 设计基线：
>   - `docs/item_stock.md`（"通用 SaaS 进销存"分层能力体系）
>   - `docs/categories.md`（分类作为独立主数据：owner 双层 + type 三态 + item_type_scope + 树形 + code）
> 业务范围：仅"自包含三件套"业务 Action（手动调整 / 盘点 / 报损），但 Schema 一次到位。

---

## 1. 目标

让本项目从"无库存"演进到"通用 SaaS 进销存第一期"，具备以下三件能力：

1. **多租户库存数据隔离**：所有库存数据按 `tenant_id` 强制隔离；门店级 `store_id` / 库存主体 `stock_owner_id` 进一步细化。
2. **三层 Feature Toggle**：租户级 `tenant_inventory_configs`（11 字段）→ 门店级 `store_inventory_configs`（5 字段）→ SKU 级策略 `product_inventory_policies`，三层按 AND 语义校验。
3. **三件套业务 Action**：手动调整、单 SKU 盘点、单 SKU 报损，全部通过 `stock_txns` 单写流水 + `stock_balances` 同步实现，无单据状态机。

第一期 Schema 完整（13 张表 + BOM 表预留），但业务 Action 仅覆盖三件套；其他能力（采购 / 调拨 / 生产 / 批次写入 / 多货架写入）的表 / 字段已就位，逻辑留待后续阶段。

## 2. 架构

数据流核心：

```
[业务 Action]
     │
     ▼
[校验三层 toggle:tenant→store→sku]
     │
     ▼
[行级悲观锁 stock_balances]
     │
     ▼
[INSERT stock_txns（append-only 流水）]
     │
     ▼
[UPDATE stock_balances.{available|reserved|in_transit|damaged}_qty]
     │
     ▼
[发布 StockChanged 事件]
```

撤销机制：写一条反向 `stock_txns`（`qty_change` 取反，`meta.cancels_txn_id` 指向原记录），并对 `stock_balances` 反向更新。第一期不做撤销时间窗校验。

## 3. 技术栈

- Laravel 13（沿用项目现有版本）
- PHP 8.3+
- MySQL 8.x（行级锁 + JSON 列）
- Pest 测试框架（沿用项目）
- Vue 3 + Inertia.js（沿用项目前端栈）
- 模块化目录：`app/Modules/{Catalog,Inventory}/`

## 4. 数据模型（13 张表）

### 4.1 Catalog 区（4 张表）

#### 4.1.1 `categories`（按 `docs/categories.md` 完整设计）

```
id                CHAR(26)   ULID 主键
tenant_id         CHAR(26)   FK tenants.id
owner_type        ENUM('TENANT','STORE')   默认 'TENANT'
owner_store_id    CHAR(26) NULL            仅当 owner_type='STORE' 时填
category_type     ENUM('BUSINESS','INVENTORY','BOTH')
                                            BUSINESS=经营分类（前台展示/营销/销售报表）
                                            INVENTORY=库存物料分类（采购/原料/库存报表）
                                            BOTH=两者通用
item_type_scope   ENUM('SALE_PRODUCT','RAW_MATERIAL','SEMI_FINISHED',
                       'FINISHED_GOOD','SERVICE','PACKAGE','ALL')
                                            限制本分类只能挂哪些 item.item_type；
                                            'ALL' 表示不限。挂载时强校验。
parent_id         CHAR(26) NULL            自引用 FK categories.id（同 tenant + 同 owner）
name              VARCHAR(100)
code              VARCHAR(64) NULL          人类编码，例如 'B-DRINK-MILKTEA' / 'I-RAW-MILK'
level             SMALLINT     默认 1       层级，根=1
path              VARCHAR(500) 默认 ''      物化路径，例 '/01J.../01J.../'，根为 '/'
sort_no           INT          默认 0       同级排序权重
status            ENUM('active','disabled')  默认 'active'
created_at        TIMESTAMP
updated_at        TIMESTAMP
deleted_at        TIMESTAMP NULL  软删除
```

索引：
- `UNIQUE (tenant_id, code)` —— code 非空时租户内唯一（MySQL 8 函数索引或软校验）
- `UNIQUE (tenant_id, owner_type, owner_store_id, parent_id, name)` —— 同父同 owner 下名称不重
- `INDEX (tenant_id, category_type, status)`
- `INDEX (tenant_id, owner_type, owner_store_id)`
- `INDEX (parent_id)`
- `INDEX (tenant_id, path)` —— 子树查询前缀匹配

约束（应用层）：
- `owner_type='STORE'` 时 `owner_store_id` 必填、且 `owner_store_id.tenant_id = categories.tenant_id`
- `parent_id` 必须与本行同一 `(tenant_id, owner_type, owner_store_id)`
- `level = parent.level + 1`；`path = parent.path + parent.id + '/'`（根行 `path = '/'`、`level = 1`）
- 树深度建议不超过 4 级（应用层软校验，超出告警不阻断）
- 删除规则：若分类下存在子分类或被 `items.business_category_id` / `items.inventory_category_id` 引用，则禁止物理删除，仅允许 `status='disabled'` 停用
- 跨门店挂载校验：门店 A 的商品禁止挂门店 B 的私有分类（`owner_type='STORE'` 时 `owner_store_id` 必须等于 item 的 `owner_store_id`，租户公共分类不限）

#### 4.1.2 `items`（统一物料主数据，取代旧 `goods`）

```
id                CHAR(26)   ULID 主键
tenant_id         CHAR(26)   FK tenants.id
owner_type        ENUM('TENANT','STORE')  默认 'TENANT'
owner_store_id    CHAR(26) NULL  仅当 owner_type='STORE' 时填
item_type         ENUM('SALE_PRODUCT','RAW_MATERIAL','SEMI_FINISHED',
                       'FINISHED_GOOD','SERVICE','PACKAGE')
item_name             VARCHAR(120)
business_category_id  CHAR(26) NULL  FK categories.id  经营分类（category_type ∈ {BUSINESS, BOTH}）
inventory_category_id CHAR(26) NULL  FK categories.id  库存物料分类（category_type ∈ {INVENTORY, BOTH}）
unit                  VARCHAR(20)    自由文本（如 '瓶'/'袋'/'g'/'ml'/'份'）
sku_enabled       BOOLEAN    默认 TRUE；FALSE 时该 item 无独立 SKU 行
inventory_enabled BOOLEAN    默认 TRUE；与 SKU 级策略 AND 生效
status            ENUM('active','off_shelf')  默认 'active'
created_at        TIMESTAMP
updated_at        TIMESTAMP
deleted_at        TIMESTAMP NULL
```

索引：`INDEX (tenant_id, status)`、`INDEX (tenant_id, item_type)`、`INDEX (tenant_id, business_category_id)`、`INDEX (tenant_id, inventory_category_id)`。

约束（应用层）：
- 当 `owner_type='STORE'` 时 `owner_store_id` 必填（避免跨租户 store FK 复杂性）
- 挂 `business_category_id` 时校验该分类 `category_type ∈ {BUSINESS, BOTH}` 且 `item_type_scope` 包含本 item 的 `item_type`（或为 `'ALL'`）
- 挂 `inventory_category_id` 时校验该分类 `category_type ∈ {INVENTORY, BOTH}` 且 `item_type_scope` 包含本 item 的 `item_type`（或为 `'ALL'`）
- 挂分类的 owner 校验：租户公共分类（`owner_type='TENANT'`）任何 item 都可挂；门店私有分类（`owner_type='STORE'`）仅允许同 store 的门店私有 item 挂

#### 4.1.3 `item_skus`（item_stock.md 文档名 `item_sku`）

```
id                  CHAR(26)
tenant_id           CHAR(26)   冗余字段，便于跨表查询不需 join items
item_id             CHAR(26)   FK items.id
spec_json           JSON       规格描述，例：{"size":"500ml","color":"red"}
barcode             VARCHAR(64) NULL
sale_price_cents    INT        销售单价，分
cost_price_cents    INT        成本单价，分（可由 stock_quants 单价更新）
inventory_enabled   BOOLEAN    默认 TRUE；SKU 级开关
created_at          TIMESTAMP
updated_at          TIMESTAMP
deleted_at          TIMESTAMP NULL
```

索引：`UNIQUE (tenant_id, barcode)`(barcode 非空时唯一)、`INDEX (item_id)`。

约束：`item.sku_enabled=FALSE` 时该 item 不允许有 sku 行（应用层校验）。

#### 4.1.4 `product_inventory_policies`（item_stock.md 行 81-90 单独表）

```
id                     CHAR(26)
tenant_id              CHAR(26)
sku_id                 CHAR(26)   FK item_skus.id，UNIQUE（一对一）
inventory_track_type   ENUM('NONE','FINISHED_GOOD','RAW_MATERIAL','BOTH')
                                  默认 'FINISHED_GOOD'
stock_deduct_mode      ENUM('SALE_DEDUCT','MANUAL_DEDUCT','PRODUCTION_DEDUCT')
                                  默认 'MANUAL_DEDUCT'（第一期无 SALE / PRODUCTION）
allow_negative_stock   BOOLEAN    默认 FALSE
batch_required         BOOLEAN    默认 FALSE
expiry_required        BOOLEAN    默认 FALSE
created_at             TIMESTAMP
updated_at             TIMESTAMP
```

约束：每个 sku 创建时自动建一行默认 policy（应用层 listener）。

### 4.2 Toggle 区（2 张表）

#### 4.2.1 `tenant_inventory_configs`（item_stock.md 行 39-58）

```
id                                CHAR(26)
tenant_id                         CHAR(26)  UNIQUE
inventory_enabled                 BOOLEAN  默认 TRUE
multi_location_enabled            BOOLEAN  默认 FALSE
production_enabled                BOOLEAN  默认 FALSE
purchase_enabled                  BOOLEAN  默认 FALSE
transfer_enabled                  BOOLEAN  默认 FALSE
stocktaking_enabled               BOOLEAN  默认 TRUE
negative_stock_allowed            BOOLEAN  默认 FALSE
inventory_cost_method             ENUM('FIFO','MOVING_AVG','STANDARD')
                                            默认 'MOVING_AVG'
expiry_management_enabled         BOOLEAN  默认 FALSE
batch_management_enabled          BOOLEAN  默认 FALSE
auto_deduct_raw_material_enabled  BOOLEAN  默认 FALSE
created_at                        TIMESTAMP
updated_at                        TIMESTAMP
```

每个租户创建时自动建一行（默认值即上）。

#### 4.2.2 `store_inventory_configs`（item_stock.md 行 59-72）

```
id                              CHAR(26)
tenant_id                       CHAR(26)
store_id                        CHAR(26)  UNIQUE
inventory_enabled               BOOLEAN   默认 TRUE
multi_location_enabled          BOOLEAN   默认 FALSE
default_stock_mode              VARCHAR(20)  默认 'SIMPLE'（自由字符串，文档未定枚举）
production_enabled              BOOLEAN   默认 FALSE
allow_direct_stock_adjustment   BOOLEAN   默认 TRUE（第一期手动调整必须开）
created_at                      TIMESTAMP
updated_at                      TIMESTAMP
```

每个 store 创建时自动建一行。

### 4.3 库存核心区（5 张表）

#### 4.3.1 `stock_owners`（item_stock.md 行 168-181）

```
id              CHAR(26)
tenant_id       CHAR(26)
owner_type      ENUM('STORE','WAREHOUSE','PRODUCTION_AREA')
owner_ref_id    CHAR(26)   指向 stores.id 或 warehouses.id（第一期仅 STORE）
name            VARCHAR(80)
status          ENUM('active','disabled')  默认 'active'
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

第一期约束：每个 store 创建时自动建一条 `owner_type='STORE', owner_ref_id=store.id` 的 stock_owner（应用层 listener）。

#### 4.3.2 `stock_locations`（item_stock.md 行 187-203）

```
id                CHAR(26)
tenant_id         CHAR(26)
stock_owner_id    CHAR(26)   FK stock_owners.id
location_code     VARCHAR(40)
location_name     VARCHAR(80)
location_type     ENUM('SHELF','FREEZER','DISPLAY','BACKROOM')
status            ENUM('active','disabled')  默认 'active'
created_at        TIMESTAMP
updated_at        TIMESTAMP
```

索引：`UNIQUE (stock_owner_id, location_code)`。

第一期约束：每个 stock_owner 创建时自动建一条 `location_code='DEFAULT', location_type='SHELF'`，所有 stock_balance 行的 `location_id` 都指向此默认库位（不为 NULL）。

#### 4.3.3 `stock_balances`（item_stock.md 行 211-225）

```
id                  CHAR(26)
tenant_id           CHAR(26)
stock_owner_id      CHAR(26)
location_id         CHAR(26)   非空，指向默认或具名 location
sku_id              CHAR(26)
available_qty       DECIMAL(18,4)  默认 0
reserved_qty        DECIMAL(18,4)  默认 0
in_transit_qty      DECIMAL(18,4)  默认 0
damaged_qty         DECIMAL(18,4)  默认 0
version             INT            乐观锁版本号，默认 0
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

索引：`UNIQUE (tenant_id, stock_owner_id, location_id, sku_id)`、`INDEX (tenant_id, sku_id)`。

写入策略：`SELECT ... FOR UPDATE` 行级悲观锁；`version` 仅作为可选乐观锁备用，不强制使用。

#### 4.3.4 `stock_quants`（item_stock.md 行 229-247）

```
id              CHAR(26)
tenant_id       CHAR(26)
stock_owner_id  CHAR(26)
location_id     CHAR(26)
sku_id          CHAR(26)
batch_no        VARCHAR(64) NULL
expiry_date     DATE NULL
unit_cost_cents INT
qty             DECIMAL(18,4)
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

索引：`INDEX (tenant_id, sku_id, expiry_date)`、`INDEX (tenant_id, stock_owner_id, location_id, sku_id)`。

第一期写入：**无**。表存在但 Action 不写入；批次启用时（二期）配套写入逻辑。

#### 4.3.5 `stock_txns`（item_stock.md 行 261-289，文档表名 `stock_txn`）

```
id                BIGINT UNSIGNED AUTO_INCREMENT  主键（区别于其他 ULID 表）
tenant_id         CHAR(26)
biz_type          ENUM(…见下…)
biz_order_type    VARCHAR(40) NULL  指向单据类型（第一期为空，预留）
biz_order_id      CHAR(26) NULL     指向单据 id（第一期为空，预留）
stock_owner_id    CHAR(26)
location_id       CHAR(26)
sku_id            CHAR(26)
qty_change        DECIMAL(18,4)  正负有意义（IN 为正，OUT 为负）
unit_cost_cents   INT NULL
amount_cents      INT NULL       qty_change * unit_cost_cents（应用层算）
direction         ENUM('IN','OUT','FREEZE','RELEASE')
occurred_at       TIMESTAMP
operator_id       CHAR(26)       FK users.id
meta_json         JSON           扩展信息（cancels_txn_id 等）
created_at        TIMESTAMP
```

`biz_type` 枚举（严格按 item_stock.md 行 266-277，共 12 值）：
- `ADJUSTMENT`、`STOCKTAKE_PROFIT`、`STOCKTAKE_LOSS`、`DAMAGE_OUT` — 第一期使用
- `PURCHASE_IN`、`SALE_OUT`、`RETURN_IN`、`RETURN_OUT`、`TRANSFER_OUT`、`TRANSFER_IN`、`PRODUCTION_CONSUME`、`PRODUCTION_OUTPUT` — 第一期不写

`ADJUSTMENT` 通过 `meta_json.subtype` 区分初始化 / 手动调整：
- `meta.subtype='INITIAL'`：初始化入库（第一次给该 sku/owner/location 建立库存）
- `meta.subtype='MANUAL'`：日常手动加减（盘盈盘亏不属此类，走 STOCKTAKE_*）

撤销流水（reverse）的 `biz_type` **沿用原 txn 的 biz_type**（不新增独立枚举值），通过 `meta_json.cancels_txn_id` 区分撤销关系。

索引：`INDEX (tenant_id, stock_owner_id, sku_id, occurred_at)`、`INDEX (tenant_id, biz_type, occurred_at)`、`INDEX (tenant_id, biz_order_type, biz_order_id)`。

第一期不分区；BIGINT id 留足空间。后期视体量做月分区迁移。

### 4.4 BOM 区（2 张表，第一期预建无写入）

#### 4.4.1 `boms`（item_stock.md 行 365-374）

```
id              CHAR(26)
tenant_id       CHAR(26)
output_sku_id   CHAR(26)   产出 SKU
output_qty      DECIMAL(18,4)
bom_type        ENUM('STANDARD','STORE_CUSTOM')
store_id        CHAR(26) NULL  仅 STORE_CUSTOM 时填
status          ENUM('active','disabled')  默认 'active'
created_at      TIMESTAMP
updated_at      TIMESTAMP
deleted_at      TIMESTAMP NULL
```

#### 4.4.2 `bom_components`（item_stock.md 行 378-385）

```
id                 CHAR(26)
bom_id             CHAR(26)   FK boms.id
component_sku_id   CHAR(26)   原料 SKU
consume_qty        DECIMAL(18,4)
loss_rate          DECIMAL(6,4)  默认 0.0000（10% 写 0.1000）
sequence_no        SMALLINT     默认 0
created_at         TIMESTAMP
updated_at         TIMESTAMP
```

第一期无写入逻辑、无 UI、无 Action；表存在以确认架构方向。

## 5. 三层 Toggle 校验链路

任何业务 Action 入口（HTTP / CLI / Job）都必须按以下顺序校验，**任一层失败即抛 `InventoryDisabledException`**：

```
1. tenant_inventory_configs[tenant_id].inventory_enabled == TRUE
2. store_inventory_configs[store_id].inventory_enabled == TRUE
3. items[item_id].inventory_enabled == TRUE
4. item_skus[sku_id].inventory_enabled == TRUE
5. product_inventory_policies[sku_id].inventory_track_type != 'NONE'
```

实现方式：`InventoryGuard` 类（`app/Modules/Inventory/Support/InventoryGuard.php`），暴露 `assertEnabled(tenant_id, store_id, sku_id)` 静态方法，所有 Action 入口调用。

`negative_stock_allowed` 校验：写入时若导致 `available_qty < 0`，按 `tenant.negative_stock_allowed AND policy.allow_negative_stock` 决定是否允许。两层 AND 语义，任一为 FALSE 则禁止。

## 6. 业务 Action（三件套）

### 6.1 `AdjustStockAction`

签名：
```php
AdjustStockAction::handle(
    tenant_id, stock_owner_id, location_id, sku_id,
    qty_change DECIMAL,    // 正数 IN，负数 OUT
    direction ENUM,        // 'IN' | 'OUT'
    subtype ENUM,          // 'INITIAL' | 'MANUAL'（写入 meta_json.subtype）
    operator_id, meta_json?
): stock_txn_id
```

事务流程：
1. `InventoryGuard::assertEnabled(...)`
2. `SELECT * FROM stock_balances WHERE ... FOR UPDATE`（不存在则 INSERT 一行 qty=0 后 lock）
3. 校验 negative_stock 规则
4. `INSERT stock_txns (qty_change, direction, biz_type='ADJUSTMENT', meta_json={subtype, ...}, ...)`
5. `UPDATE stock_balances SET available_qty = available_qty + qty_change, version = version + 1`
6. 提交事务，发布 `StockChanged` 事件

### 6.2 `StocktakeAction`

签名：
```php
StocktakeAction::handle(
    tenant_id, stock_owner_id, location_id, sku_id,
    actual_qty DECIMAL,    // 实盘数
    operator_id, note?
): stock_txn_id
```

事务流程：
1. `InventoryGuard::assertEnabled(...)`
2. 行级锁 stock_balances，读 `current_qty = available_qty`
3. `delta = actual_qty - current_qty`
4. `delta > 0` → `biz_type='STOCKTAKE_PROFIT', direction='IN'`
5. `delta < 0` → `biz_type='STOCKTAKE_LOSS', direction='OUT'`
6. `delta == 0` → 不写流水，直接返回 null
7. 写 stock_txn（meta_json 含 `book_qty`、`actual_qty`、`note`）
8. 更新 stock_balance

### 6.3 `DamageAction`

签名：
```php
DamageAction::handle(
    tenant_id, stock_owner_id, location_id, sku_id,
    qty DECIMAL,           // 报损数量（恒正）
    unit_cost_cents,
    operator_id, reason?
): stock_txn_id
```

事务流程：
1. `InventoryGuard::assertEnabled(...)`
2. 行级锁 stock_balances
3. 校验 `available_qty >= qty`（除非 negative_stock_allowed）
4. `INSERT stock_txns (qty_change=-qty, direction='OUT', biz_type='DAMAGE_OUT', unit_cost_cents, amount_cents=qty*unit_cost_cents)`
5. `UPDATE stock_balances SET available_qty -= qty`（**第一期单桶变动：物理出库**）

第一期 `damaged_qty` 桶**不写入**：报损视为物理出库，库存数量直接减少。`damaged_qty` 桶字段建出但保持 0，预留给二期"标记报损 + 处理报废"两段式流程使用。
保持 4 桶字段一次到位，不变量"4 桶之和 = 流水净累积"成立（其余 3 桶第一期恒为 0）。

### 6.4 `ReverseStockTxnAction`

签名：
```php
ReverseStockTxnAction::handle(txn_id BIGINT, operator_id): reverse_txn_id
```

事务流程：
1. 读原 txn，校验 `meta_json.cancels_txn_id` 不存在（已撤销不能再撤）
2. 行级锁 stock_balances
3. 写反向 stock_txn（`qty_change` 取反，`direction` 反转，`meta_json.cancels_txn_id = original_txn_id`）
4. 反向更新 stock_balance：原 IN 改为 -qty，原 damaged_qty 增量也反向
5. 在原 txn 的 meta_json 写 `reversed_by_txn_id = new_txn_id`（需要二次写入原 txn 行，但 stock_txn 是 append-only ⇒ 改为：维护 `stock_txn_reversals` 索引表，或仅靠新 txn 的 meta 反查）

实现选择：不更新原 txn（保持 append-only 严格性），通过 `WHERE meta_json->>'$.cancels_txn_id' = ?` 查询是否被撤销。

撤销笔的 `biz_type` 沿用原笔（如原 STOCKTAKE_PROFIT 撤销后还是 STOCKTAKE_PROFIT，但 qty_change 取反、direction 反转、`meta.cancels_txn_id` 指向原 txn）。

`STOCKTAKE_LOSS` / `DAMAGE_OUT` 撤销时，原本扣减的 available_qty 要加回来，要校验 negative_stock 规则反向场景下不需要校验（因为是恢复）。

第一期不做撤销时间窗校验（任何时刻都能撤）；后期可在 `tenant_inventory_configs` 加 `reversal_window_hours` 字段。

## 7. 删除旧 `goods` 模块的迁移路径

### 7.1 删除清单

文件 / 目录：
- `app/Modules/Catalog/Models/Good.php`
- `app/Modules/Catalog/Database/Factories/GoodFactory.php`
- `app/Modules/Catalog/Database/Migrations/2026_05_08_000061_create_goods_table.php`
- `app/Modules/Catalog/Tests/Feature/TenantGoodWebTest.php`
- `app/Modules/Catalog/Enums/GoodStatus.php`（被 ItemStatus 替代）
- 任何 Goods Controller / Page Vue / 路由 / 权限点

权限点删除（认证表 seed）：
- `goods.read`、`goods.write` → 改为 `items.read`、`items.write`

### 7.2 现有 `categories` 表升级（按 `docs/categories.md`）

旧 `categories` 表结构（仅 id/tenant_id/name/sort/timestamps）**整体重建**：

- 旧迁移文件直接删除（项目未上生产，无需 ALTER 链路）
- 新迁移按 §4.1.1 完整字段建表
- 新增字段：`owner_type` / `owner_store_id` / `category_type` / `item_type_scope` / `parent_id` / `code` / `level` / `path` / `status`
- 字段重命名：旧 `sort` → 新 `sort_no`
- FK 迁移：旧 `goods.category_id` → 新 `items.business_category_id` + `items.inventory_category_id`（双字段）
- 不需要数据迁移（项目还未上生产，开发阶段直接重置）

### 7.3 路由 / 页面 / i18n 改名

- `/tenant/goods*` → `/tenant/items*`
- `resources/js/pages/tenant/Goods/*` → `resources/js/pages/tenant/Items/*`
- 侧栏 `nav.goods` 国际化键改为 `nav.items`
- `useNavigation.ts` 菜单条目改名

## 8. 权限点清单（新增）

```
items.read, items.write
item_skus.read, item_skus.write        ← 嵌在 item 详情中操作
categories.read, categories.write
inventory.read                         ← stock_balances / stock_quants 查询
inventory.adjust                       ← 手动调整
stocktake.write                        ← 单 SKU 盘点
damage.write                           ← 单 SKU 报损
stock_txn.read                         ← 流水查询
stock_txn.reverse                      ← 撤销任一笔流水
inventory_config.read                  ← 读 tenant / store config
inventory_config.update                ← 写 tenant / store config
inventory_policy.read                  ← 读 SKU 级 policy
inventory_policy.update                ← 写 SKU 级 policy
```

种子数据：把上述 16 个权限点 seed 进 `permissions` 表，并预置三类租户角色（仅供参考，按现有 RBAC 模块约定）：
- `tenant_admin`：全部库存权限
- `store_manager`：除 `inventory_config.update` / `inventory_policy.update` 外全部
- `store_clerk`：仅 `*.read` + `inventory.adjust` + `stocktake.write`

## 9. UI 范围（第一期）

| 页面 | 路径 | 说明 |
|---|---|---|
| 商品（items）列表 | `/tenant/items` | 按 item_type / category / status 筛选 |
| 商品创建 | `/tenant/items/create` | 表单含 inventory_enabled / item_type / unit |
| 商品编辑 | `/tenant/items/{id}/edit` | 含内联 SKU 列表（增删改） + 内联 policy 编辑 |
| 单位字典 | （无独立页面） | unit 字段直接 input；不建 units 表 |
| 分类列表 | `/tenant/categories` | 树形展示（parent/level/path），按 owner_type / category_type 筛选；支持启用/停用切换；行内"添加子分类" |
| 分类创建/编辑 | `/tenant/categories/create` / `/tenant/categories/{id}/edit` | 表单：owner_type、category_type、item_type_scope、parent（同 owner 范围内树形选择器）、name、code、sort_no、status |
| 库存查询 | `/tenant/stock` | 按 store / item / sku 维度展示 4 桶余额 |
| 流水查询 | `/tenant/stock/txns` | 倒序列表，支持按 biz_type / 日期 / sku 筛选；行操作"撤销" |
| 手动调整 | `/tenant/stock/adjust` 或弹窗 | 选 store / sku / qty / direction，写 INITIAL_STOCK 或 MANUAL_ADJUST |
| 盘点 | `/tenant/stock/stocktake` 或弹窗 | 选 store / sku / 实盘数，写 STOCKTAKE_PROFIT/LOSS |
| 报损 | `/tenant/stock/damage` 或弹窗 | 选 store / sku / qty / 单价 / 原因 |
| 租户库存配置 | `/tenant/settings/inventory` | tenant_inventory_configs 11 字段表单 |
| 门店库存配置 | `/tenant/stores/{id}/inventory` | store_inventory_configs 5 字段表单 |

侧栏新增"库存"模块，含子菜单：库存 / 流水 / 调整 / 盘点 / 报损 / 配置。

## 10. 测试策略

### 10.1 单元测试
- `InventoryGuard::assertEnabled` 五层 toggle 关闭场景全覆盖
- 三件套 Action 在各种 negative_stock / 不存在 balance 行 / 跨租户场景下的行为

### 10.2 Feature 测试（Pest + RefreshDatabase）
- `TenantCategoryWebTest`：列表（树形展示）/ 创建（含 parent_id 路径计算）/ 编辑 / 启用停用、跨租户隔离、跨门店私有分类隔离、有子分类或被 item 引用时禁删、code 租户内唯一冲突
- `TenantItemWebTest`：item 列表 / 创建 / 编辑 / SKU 内联 / policy 内联，覆盖原 TenantGoodWebTest 全部用例；含 business_category_id / inventory_category_id 挂载与 category_type / item_type_scope 校验
- `StockBalanceQueryTest`：跨租户隔离、跨门店隔离
- `StockAdjustActionTest`：Initial / Manual + / Manual -，含 negative_stock 边界
- `StocktakeActionTest`：profit / loss / 等于零三种情形
- `DamageActionTest`：含 damaged_qty 桶变动
- `ReverseStockTxnTest`：原 IN / 原 OUT / 已撤销不能再撤、跨租户不能撤
- `ToggleEnforcementTest`：五层 toggle 任一关闭都 403/异常
- `PermissionTest`：13 个新权限点的 seed 与角色绑定

### 10.3 集成不变量测试
- 任意一笔 stock_txn 写入后，对应 stock_balance 行的 4 桶之和等于历史 stock_txn 净累积
- `stock_txn.qty_change` 总和按 sku 分组 = 当前 `stock_balance.available_qty + reserved + in_transit + damaged`

## 11. 第一期明确不做的事

- **批次写入**：`stock_quants` 表存在但 Action 不写
- **多货架写入**：`stock_locations` 表只有默认 'DEFAULT' 一条；UI 不暴露多 location 选择
- **`reserved_qty` / `in_transit_qty` / `damaged_qty` 桶**：字段建出但第一期恒为 0；
  - reserved_qty 需配套订单/预占机制（第一期无订单模块）
  - in_transit_qty 需配套调拨模块（第一期不做调拨）
  - damaged_qty 需配套两段式报损（标记 + 处理报废，第一期 damage 直接物理出库）
- **BOM 写入与销售扣原料**：`boms` / `bom_components` 表存在但无 Action / UI
- **采购模块**：不建 Purchasing 模块、不建 suppliers 表
- **调拨模块**：不做 transfer 逻辑（Schema 已支持：`stock_txns.biz_type` 含 TRANSFER_OUT/IN）
- **生产模块**：不做 production 逻辑
- **多 SKU 单据 / 单据状态机 / 撤销时间窗**：item_stock.md 文档无设计，第一期完全不做
- **成本核算（FIFO / 移动加权）**：`stock_txns.unit_cost_cents` 仅作为输入记录，不做成本回算
- **stock_owner.owner_type='WAREHOUSE'/'PRODUCTION_AREA'**：表支持，UI 不暴露

## 12. 阶段划分

| 阶段 | 范围 |
|---|---|
| **第一期（本 spec）** | 13 张表 + 三层 toggle + 三件套 Action + 撤销 + 商品/SKU/Policy UI + 配置 UI |
| 第二期 | 批次 / 保质期写入；多货架 UI；盘点 / 报损升级为多行单据 |
| 第三期 | 采购模块 + 供应商 + 采购单 / 采购退货 |
| 第四期 | 调拨（含 in_transit_qty 写入） |
| 第五期 | BOM / 生产 / 销售扣原料 |

## 13. 关键决策记录

| 决策 | 选择 | 来源 |
|---|---|---|
| 业务定位 | 通用 SaaS 进销存 | 用户 2026-05-08 |
| 商品体系 | 统一 Item 主数据 + SKU 明细 | 用户 |
| 库存核心表 | item_stock.md 多状态版（4 桶） | 用户 |
| Toggle 颗粒度 | 三层骨架 + item_stock.md 完整字段集 | 用户 |
| 单据范围 | 自包含三件套（adjust / stocktake / damage） | 用户 |
| Schema 严格度 | 严格按 item_stock.md，能改都改正 | 用户 |
| Goods 处置 | 完全删除，items 取代 | 用户 |
| 偏离修正 | 拆出 product_inventory_policies / 删 units 表 / 不建单据表 | 用户（严格按文档） |
| 分类体系 | 按 `docs/categories.md` 完整重建：单 categories 表 + owner_type 双层（TENANT/STORE）+ category_type 三态（BUSINESS/INVENTORY/BOTH）+ item_type_scope 强校验 + 树形（parent_id+level+path）+ code 租户内唯一；item 上 business_category_id + inventory_category_id 双字段（方案 A，未来按需扩 item_category_rel） | 用户 2026-05-08 |

## 14. 不做项的明示意图

第一期之所以不建盘点单 / 报损单的"单据 + lines"两表结构，是因为 item_stock.md **完全没有这两张表的字段设计**（仅在第八节列了"模块名"）。严格遵循文档 = 不发明文档外的表结构。代价是：
- 盘点 / 报损只能单 SKU 操作（一次一笔流水）
- 撤销靠反向流水，无 48h 时间窗 / 状态机
- 升级时新增单据表 + 让 `stock_txns.biz_order_type/id` 指向单据 id 即可（字段已就位）

这是有意识的取舍，记录于此以备未来回看时不致误解。
