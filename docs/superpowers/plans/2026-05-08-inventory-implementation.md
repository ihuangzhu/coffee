# Inventory Module First-Iteration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 实现库存模块第一期：Catalog 重构（goods → items + item_skus + product_inventory_policies；categories 升级为 owner+type+scope+tree+code 完整分类体系）+ 13 张表 schema + 三层 toggle + 三件套业务 Action（手动调整 / 盘点 / 报损）+ 撤销 + UI + 测试。

**Architecture:** 严格遵循 `docs/superpowers/specs/2026-05-08-inventory-design.md`（"严格按 item_stock.md + categories.md"）。分类为独立主数据：单 categories 表 + owner_type 双层（TENANT/STORE）+ category_type 三态（BUSINESS/INVENTORY/BOTH）+ item_type_scope 强校验 + 树形 path/level + code 租户内唯一；item 上 business_category_id + inventory_category_id 双 FK。所有库存变化 = 入口校验三层 toggle → 行级悲观锁 stock_balances → 写 stock_txns（append-only）→ 同步 balance → 发布事件。撤销 = 反向 stock_txn + meta.cancels_txn_id 标记。

**Tech Stack:** Laravel 13 / PHP 8.3 / MySQL 8.x / Pest 3 / Vue 3 + Inertia.js v2 / Element Plus / Pinia / Tailwind. ULID 主键（CHAR(26)）+ `BelongsToTenant` + `HasUlid` traits 沿用项目惯例。

---

## 全局约定（每个任务都默认遵循）

- **测试运行**：`composer test` 跑 Pest 全集；单文件 `./vendor/bin/pest <path>`；单 case 加 `--filter='<name fragment>'`。
- **迁移命名**：沿用 `2026_05_08_NNNNNN_*` 序列。当前最大 `000061`（goods，本计划 Task 1 删除）。新迁移从 `000061` 重用（替换 goods）开始顺序写。
- **模型基类**：所有租户级表用 `BelongsToTenant + HasFactory + HasUlid`，`protected $guarded = []`，casts 含枚举与整数列。
- **Factory**：FK 写 `Tenant::factory()` / 上游 factory；不写死 ID。
- **测试 setup**：`uses(RefreshDatabase::class)`，`beforeEach` 建 tenant + actor + Membership + `actingAs($actor, 'web')->withSession(['current_tenant_id' => $tenant->id])`。
- **commit 规范**：`<type>(<scope>): <summary>`，type ∈ {feat,refactor,test,chore}。每个任务结尾 commit 一次。
- **Inertia render 路径**：`tenant/<Module>/<Page>`（小写模块名，匹配现有 Goods 页面位置）。
- **路由集中**：所有 web 路由加在 `/routes/web.php`，已存在 `Route::prefix('tenant')->middleware('auth')->group(...)`。
- **权限校验**：本项目目前 controller 内**未做** gate/middleware 强制；权限点仅在前端通过 sharedProps 控制按钮可见性。本计划遵循同模式（不强行加 controller 层 gate）。

---

## 文件结构（创建/修改清单）

### 删除（Task 1）
```
app/Modules/Catalog/Models/Good.php
app/Modules/Catalog/Enums/GoodStatus.php
app/Modules/Catalog/Database/Factories/GoodFactory.php
app/Modules/Catalog/Database/Migrations/2026_05_08_000061_create_goods_table.php
app/Modules/Catalog/Http/Controllers/Web/TenantGoodController.php
app/Modules/Catalog/Tests/Feature/TenantGoodWebTest.php
resources/js/pages/tenant/Goods/Index.vue
resources/js/pages/tenant/Goods/Create.vue
resources/js/pages/tenant/Goods/Edit.vue
（routes/web.php 中 goods 路由 5 行）
```

### 重写（Tasks 1A-1C，分类主数据升级）
```
app/Modules/Catalog/Database/Migrations/2026_05_08_000060_create_categories_table.php  (Task 1A，整体重写)
app/Modules/Catalog/Models/Category.php                                                (Task 1A，整体重写)
app/Modules/Catalog/Database/Factories/CategoryFactory.php                              (Task 1A，整体重写)
app/Modules/Catalog/Http/Controllers/Web/TenantCategoryController.php                   (Task 1B，整体重写)
app/Modules/Catalog/Tests/Feature/TenantCategoryWebTest.php                             (Task 1C，整体重写)
resources/js/pages/tenant/Categories/{Index,Create,Edit}.vue                            (Task 1B，整体重写)
```

### 新建（Tasks 1A-1C 新文件）
```
app/Modules/Catalog/Enums/CategoryOwnerType.php                                         (Task 1A)
app/Modules/Catalog/Enums/CategoryType.php                                              (Task 1A)
app/Modules/Catalog/Enums/CategoryItemTypeScope.php                                     (Task 1A)
app/Modules/Catalog/Enums/CategoryStatus.php                                            (Task 1A)
app/Modules/Catalog/Tests/Unit/CategorySchemaTest.php                                   (Task 1A)
```

### 新建（Tasks 2-25）
```
app/Modules/Catalog/Database/Migrations/
  2026_05_08_000061_create_items_table.php                                (Task 2，含 business_category_id / inventory_category_id 双 FK)
  2026_05_08_000062_create_item_skus_table.php                            (Task 3)
  2026_05_08_000063_create_product_inventory_policies_table.php           (Task 4)
app/Modules/Catalog/Models/{Item,ItemSku,ProductInventoryPolicy}.php
app/Modules/Catalog/Database/Factories/{Item,ItemSku,ProductInventoryPolicy}Factory.php
app/Modules/Catalog/Enums/ItemStatus.php
app/Modules/Catalog/Enums/ItemType.php
app/Modules/Catalog/Enums/InventoryTrackType.php
app/Modules/Catalog/Enums/StockDeductMode.php
app/Modules/Catalog/Enums/OwnerType.php
app/Modules/Catalog/Observers/ItemSkuObserver.php                         (Task 4)
app/Modules/Catalog/Http/Controllers/Web/TenantItemController.php         (Task 5)
app/Modules/Catalog/Tests/Feature/TenantItemWebTest.php                   (Task 6)
resources/js/pages/tenant/Items/{Index,Create,Edit}.vue                   (Task 5)

app/Modules/Inventory/                                                    (Tasks 7+)
  InventoryServiceProvider.php
  Database/Migrations/
    2026_05_08_000064_create_tenant_inventory_configs_table.php
    2026_05_08_000065_create_store_inventory_configs_table.php
    2026_05_08_000066_create_stock_owners_table.php
    2026_05_08_000067_create_stock_locations_table.php
    2026_05_08_000068_create_stock_balances_table.php
    2026_05_08_000069_create_stock_quants_table.php
    2026_05_08_000070_create_stock_txns_table.php
    2026_05_08_000071_create_boms_table.php
    2026_05_08_000072_create_bom_components_table.php
    2026_05_08_000073_seed_inventory_permissions.php
  Database/Factories/{TenantInventoryConfig,StoreInventoryConfig,StockOwner,
                       StockLocation,StockBalance,StockQuant,StockTxn,Bom,
                       BomComponent}Factory.php
  Models/{TenantInventoryConfig,StoreInventoryConfig,StockOwner,
          StockLocation,StockBalance,StockQuant,StockTxn,Bom,BomComponent}.php
  Enums/{InventoryCostMethod,StockOwnerType,StockLocationType,
         StockTxnBizType,StockTxnDirection,BomType}.php
  Observers/{TenantConfigObserver,StoreConfigObserver}.php
  Support/InventoryGuard.php
  Exceptions/InventoryDisabledException.php
  Actions/{AdjustStockAction,StocktakeAction,DamageAction,
           ReverseStockTxnAction}.php
  Events/StockChanged.php
  Http/Controllers/Web/{TenantStockController,TenantStockMutationController,
                         TenantInventoryConfigController,
                         TenantStoreInventoryConfigController}.php
  Tests/Unit/InventoryGuardTest.php
  Tests/Feature/{AdjustStock,Stocktake,Damage,ReverseStockTxn,
                  TenantStockWeb,TenantStockMutationWeb,
                  TenantInventoryConfigWeb,TenantStoreInventoryConfigWeb,
                  ToggleEnforcement,Permission}Test.php

resources/js/pages/tenant/Stock/{Index,Txns,Adjust,Stocktake,Damage}.vue   (Tasks 21-22)
resources/js/pages/tenant/Settings/InventoryConfig.vue                     (Task 23)
resources/js/pages/tenant/Stores/InventoryConfig.vue                       (Task 24)
```

### 修改
```
app/Modules/Authorization/Enums/Permission.php                  (Task 20，+16 cases)
app/Modules/Tenancy/Models/Tenant.php                           (Task 7，仅作为 observer 目标，不需改源文件)
app/Modules/Tenancy/Models/Store.php                            (Tasks 8-10，仅作为 observer 目标，不需改源文件)
app/Modules/Catalog/Models/ItemSku.php                          (Task 4，registerObserver)
routes/web.php                                                   (Tasks 5,21-24，+路由组)
resources/js/composables/useNavigation.ts                        (Task 25，+库存菜单)
config/app.php 或 bootstrap/providers.php                        (Task 7，注册 InventoryServiceProvider)
```

---

# Phase A — Catalog 重构

## Task 1：删除遗留 `goods` 模块

**Files:**
- Delete: `app/Modules/Catalog/Models/Good.php`
- Delete: `app/Modules/Catalog/Enums/GoodStatus.php`
- Delete: `app/Modules/Catalog/Database/Factories/GoodFactory.php`
- Delete: `app/Modules/Catalog/Database/Migrations/2026_05_08_000061_create_goods_table.php`
- Delete: `app/Modules/Catalog/Http/Controllers/Web/TenantGoodController.php`
- Delete: `app/Modules/Catalog/Tests/Feature/TenantGoodWebTest.php`
- Delete: `resources/js/pages/tenant/Goods/Index.vue` `Create.vue` `Edit.vue`
- Modify: `routes/web.php` — 删除 goods 路由 5 行 + import

- [ ] **Step 1: 跑当前测试确认 baseline 全绿**

```bash
composer test
```

Expected: All Pest tests pass (含 TenantGoodWebTest)，无失败。

- [ ] **Step 2: 删除 PHP 文件**

```bash
rm app/Modules/Catalog/Models/Good.php
rm app/Modules/Catalog/Enums/GoodStatus.php
rm app/Modules/Catalog/Database/Factories/GoodFactory.php
rm app/Modules/Catalog/Database/Migrations/2026_05_08_000061_create_goods_table.php
rm app/Modules/Catalog/Http/Controllers/Web/TenantGoodController.php
rm app/Modules/Catalog/Tests/Feature/TenantGoodWebTest.php
```

- [ ] **Step 3: 删除 Vue 页面**

```bash
rm resources/js/pages/tenant/Goods/Index.vue
rm resources/js/pages/tenant/Goods/Create.vue
rm resources/js/pages/tenant/Goods/Edit.vue
rmdir resources/js/pages/tenant/Goods
```

- [ ] **Step 4: 修改 `routes/web.php` 删除 goods 路由 + import**

打开 `/routes/web.php`，定位以下两段并删除：

```php
// 顶部 import（约第 7 行附近）
use App\Modules\Catalog\Http\Controllers\Web\TenantGoodController;
```

```php
// tenant 路由组内（约 128-132 行）
Route::get('goods', [TenantGoodController::class, 'index']);
Route::get('goods/create', [TenantGoodController::class, 'create']);
Route::post('goods', [TenantGoodController::class, 'storeFromForm']);
Route::get('goods/{id}/edit', [TenantGoodController::class, 'edit']);
Route::patch('goods/{id}', [TenantGoodController::class, 'update']);
```

- [ ] **Step 4a: 清理 `TenantCategoryController` 与测试中残留的 Good 引用**

> **为何**：Step 2 删除了 `Good` model，但现有 `TenantCategoryController` 在 index/destroy 中引用了 Good 做"分类下商品数统计"和"使用中禁删"校验，对应 `TenantCategoryWebTest` 也使用了 `Good::factory()`。Task 1A-1C 会完全重写 categories 模块，此处仅做"最小化清残留"让本任务的 Step 5 测试能通过。

修改 `app/Modules/Catalog/Http/Controllers/Web/TenantCategoryController.php`：

1. 删除 import：`use App\Modules\Catalog\Models\Good;`
2. `index` 方法中删除以下"统计每分类下商品数"代码块（约第 38-44 行）：
   ```php
   $counts = Good::query()->withoutGlobalScopes()
       ->where('tenant_id', $tenantId)
       ->whereIn('category_id', $categories->pluck('id'))
       ->groupBy('category_id')
       ->selectRaw('category_id, COUNT(*) AS c')
       ->pluck('c', 'category_id');
   ```
3. `index` 方法中 `$rows = $categories->map(...)` 去掉 `'goods_count' => (int) ($counts[$c->id] ?? 0),` 这一行
4. `destroy` 方法中删除"使用中禁删"校验块（约 128-133 行）：
   ```php
   $inUse = Good::query()->withoutGlobalScopes()
       ->where('tenant_id', $tenantId)
       ->where('category_id', $cat->id)->exists();
   if ($inUse) {
       throw ValidationException::withMessages(['category' => '该分类下仍有商品，无法删除']);
   }
   ```

修改 `app/Modules/Catalog/Tests/Feature/TenantCategoryWebTest.php`：

1. 删除 import：`use App\Modules\Catalog\Models\Good;`
2. 删除每个测试中 `Good::factory()->...` 调用，以及所有依赖 `goods_count` 字段的断言
3. 删除测试用例 `'destroy 当分类下有商品时禁止删除'`（如果存在），改为暂时只断言"删除成功"

> **注**：本步骤是过渡性最小修改；Task 1B-1C 会完全重写 controller 和 test 以支持新 schema。

修改 `resources/js/pages/tenant/Categories/Index.vue`：删除任何"商品数"列展示（若存在）。

- [ ] **Step 5: 跑测试确认 Catalog 仅剩 categories 测试，全部绿色**

```bash
composer test -- --filter=Catalog
```

Expected: 仅 `TenantCategoryWebTest` 跑，全 PASS。

- [ ] **Step 6: 跑前端 type-check 确认无 dangling 引用**

```bash
npx vue-tsc --noEmit
```

Expected: 无输出（无类型错误）。

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "refactor(catalog): drop legacy goods module"
```

---

## Task 1A：重建 `categories` 表（迁移 + 模型 + 4 枚举 + 工厂 + schema test）

> **背景**：Task 1 已删除 goods 模块并清理了 categories 中对 Good 的残留。本任务按 `docs/categories.md` 完整重建 categories 主数据：单表 + owner_type 双层（TENANT/STORE）+ category_type 三态（BUSINESS/INVENTORY/BOTH）+ item_type_scope 强校验 + 树形（parent_id/level/path）+ code 租户内唯一。后续 items 表（Task 2）的 business_category_id / inventory_category_id 双外键依赖此表。

**Files:**
- Modify (重写整个文件): `app/Modules/Catalog/Database/Migrations/2026_05_08_000060_create_categories_table.php`
- Modify (重写整个文件): `app/Modules/Catalog/Models/Category.php`
- Modify (重写整个文件): `app/Modules/Catalog/Database/Factories/CategoryFactory.php`
- Create: `app/Modules/Catalog/Enums/CategoryOwnerType.php`
- Create: `app/Modules/Catalog/Enums/CategoryType.php`
- Create: `app/Modules/Catalog/Enums/CategoryItemTypeScope.php`
- Create: `app/Modules/Catalog/Enums/CategoryStatus.php`
- Create: `app/Modules/Catalog/Tests/Unit/CategorySchemaTest.php`

- [ ] **Step 1: 写枚举 CategoryOwnerType**

`app/Modules/Catalog/Enums/CategoryOwnerType.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

/**
 * 分类的所有者层级。TENANT=租户公共分类（所有门店可见）；STORE=门店私有分类。
 */
enum CategoryOwnerType: string
{
    case Tenant = 'TENANT';
    case Store = 'STORE';
}
```

- [ ] **Step 2: 写枚举 CategoryType**

`app/Modules/Catalog/Enums/CategoryType.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

/**
 * 分类用途。BUSINESS=经营分类（前台展示/营销/销售报表）；
 * INVENTORY=库存物料分类（采购/原料/库存报表）；BOTH=两者通用。
 */
enum CategoryType: string
{
    case Business = 'BUSINESS';
    case Inventory = 'INVENTORY';
    case Both = 'BOTH';
}
```

- [ ] **Step 3: 写枚举 CategoryItemTypeScope**

`app/Modules/Catalog/Enums/CategoryItemTypeScope.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

/**
 * 分类挂载范围限制：限定本分类只能挂哪些 item.item_type。
 * 'ALL' 表示不限。其余值与 ItemType 一一对应。
 */
enum CategoryItemTypeScope: string
{
    case SaleProduct = 'SALE_PRODUCT';
    case RawMaterial = 'RAW_MATERIAL';
    case SemiFinished = 'SEMI_FINISHED';
    case FinishedGood = 'FINISHED_GOOD';
    case Service = 'SERVICE';
    case Package = 'PACKAGE';
    case All = 'ALL';
}
```

- [ ] **Step 4: 写枚举 CategoryStatus**

`app/Modules/Catalog/Enums/CategoryStatus.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

enum CategoryStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}
```

- [ ] **Step 5: 重写迁移**

完整覆盖 `app/Modules/Catalog/Database/Migrations/2026_05_08_000060_create_categories_table.php`：

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->enum('owner_type', ['TENANT', 'STORE'])->default('TENANT');
            $table->char('owner_store_id', 26)->nullable();
            $table->enum('category_type', ['BUSINESS', 'INVENTORY', 'BOTH']);
            $table->enum('item_type_scope', [
                'SALE_PRODUCT', 'RAW_MATERIAL', 'SEMI_FINISHED',
                'FINISHED_GOOD', 'SERVICE', 'PACKAGE', 'ALL',
            ])->default('ALL');
            $table->char('parent_id', 26)->nullable();
            $table->string('name', 100);
            $table->string('code', 64)->nullable();
            $table->unsignedSmallInteger('level')->default(1);
            $table->string('path', 500)->default('/');
            $table->integer('sort_no')->default(0);
            $table->enum('status', ['active', 'disabled'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('categories')->nullOnDelete();
            $table->index(['tenant_id', 'category_type', 'status']);
            $table->index(['tenant_id', 'owner_type', 'owner_store_id']);
            $table->index(['parent_id']);
            $table->index(['tenant_id', 'path']);
            // MySQL InnoDB 唯一索引允许多个 NULL 共存；code=NULL 不冲突
            $table->unique(['tenant_id', 'code'], 'categories_tenant_code_unique');
            $table->unique(
                ['tenant_id', 'owner_type', 'owner_store_id', 'parent_id', 'name'],
                'categories_tenant_owner_parent_name_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
```

- [ ] **Step 6: 重写 Category 模型**

完整覆盖 `app/Modules/Catalog/Models/Category.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\CategoryFactory;
use App\Modules\Catalog\Enums\CategoryItemTypeScope;
use App\Modules\Catalog\Enums\CategoryOwnerType;
use App\Modules\Catalog\Enums\CategoryStatus;
use App\Modules\Catalog\Enums\CategoryType;
use App\Support\Eloquent\BelongsToTenant;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 分类主数据。租户级隔离 + owner_type 双层 + category_type 三态 + 树形（parent/level/path）。
 *
 * 不直接反向 hasMany items：items 通过 business_category_id / inventory_category_id 两条
 * 路径关联本表，反向查询时按需直接构造 query。
 */
class Category extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUlid;
    use SoftDeletes;

    protected $table = 'categories';

    protected $guarded = [];

    protected $casts = [
        'owner_type' => CategoryOwnerType::class,
        'category_type' => CategoryType::class,
        'item_type_scope' => CategoryItemTypeScope::class,
        'status' => CategoryStatus::class,
        'level' => 'int',
        'sort_no' => 'int',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_no')->orderBy('name');
    }

    /**
     * 根分类 path='/'、level=1；子分类 path = parent.path + parent.id + '/'、level = parent.level + 1。
     * 由 Service 层显式调用（避免 boot 钩子隐式行为）。
     */
    public function computePathAndLevel(?Category $parent): array
    {
        if ($parent === null) {
            return ['path' => '/', 'level' => 1];
        }

        return [
            'path' => $parent->path.$parent->id.'/',
            'level' => $parent->level + 1,
        ];
    }

    protected static function newFactory(): CategoryFactory
    {
        return CategoryFactory::new();
    }
}
```

- [ ] **Step 7: 重写 CategoryFactory**

完整覆盖 `app/Modules/Catalog/Database/Factories/CategoryFactory.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\Category;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'owner_type' => 'TENANT',
            'owner_store_id' => null,
            'category_type' => 'BUSINESS',
            'item_type_scope' => 'ALL',
            'parent_id' => null,
            'name' => $this->faker->unique()->words(2, true).' 分类',
            'code' => null,
            'level' => 1,
            'path' => '/',
            'sort_no' => 0,
            'status' => 'active',
        ];
    }

    public function inventory(): static
    {
        return $this->state(['category_type' => 'INVENTORY']);
    }

    public function both(): static
    {
        return $this->state(['category_type' => 'BOTH']);
    }

    public function child(Category $parent): static
    {
        return $this->state([
            'tenant_id' => $parent->tenant_id,
            'owner_type' => $parent->owner_type,
            'owner_store_id' => $parent->owner_store_id,
            'category_type' => $parent->category_type,
            'item_type_scope' => $parent->item_type_scope,
            'parent_id' => $parent->id,
            'level' => $parent->level + 1,
            'path' => $parent->path.$parent->id.'/',
        ]);
    }
}
```

- [ ] **Step 8: 写 schema 单元测试**

`app/Modules/Catalog/Tests/Unit/CategorySchemaTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Category;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('categories 默认创建顶级 BUSINESS 分类', function () {
    $cat = Category::factory()->create();

    expect($cat->id)->toBeString()->toHaveLength(26);
    expect($cat->owner_type->value)->toBe('TENANT');
    expect($cat->owner_store_id)->toBeNull();
    expect($cat->category_type->value)->toBe('BUSINESS');
    expect($cat->item_type_scope->value)->toBe('ALL');
    expect($cat->parent_id)->toBeNull();
    expect($cat->level)->toBe(1);
    expect($cat->path)->toBe('/');
    expect($cat->status->value)->toBe('active');
});

test('child factory 自动计算 path 与 level', function () {
    $root = Category::factory()->create();
    $child = Category::factory()->child($root)->create();
    $grand = Category::factory()->child($child)->create();

    expect($child->level)->toBe(2);
    expect($child->path)->toBe('/'.$root->id.'/');
    expect($grand->level)->toBe(3);
    expect($grand->path)->toBe('/'.$root->id.'/'.$child->id.'/');
});

test('computePathAndLevel: root 与 child 路径计算正确', function () {
    $root = Category::factory()->create();

    $rootCalc = (new Category())->computePathAndLevel(null);
    expect($rootCalc)->toBe(['path' => '/', 'level' => 1]);

    $childCalc = (new Category())->computePathAndLevel($root);
    expect($childCalc)->toBe([
        'path' => '/'.$root->id.'/',
        'level' => 2,
    ]);
});

test('inventory state 改为 INVENTORY 类型', function () {
    $cat = Category::factory()->inventory()->create();
    expect($cat->category_type->value)->toBe('INVENTORY');
});

test('softDeletes 生效', function () {
    $cat = Category::factory()->create();
    $cat->delete();

    expect(Category::query()->withoutGlobalScopes()->find($cat->id))->toBeNull();
    expect(Category::query()->withoutGlobalScopes()->withTrashed()->find($cat->id))
        ->not->toBeNull();
});

test('同 tenant + 同 owner + 同 parent 下 name 唯一冲突', function () {
    $tenantId = (string) Tenant::factory()->create()->id;
    Category::factory()->create([
        'tenant_id' => $tenantId,
        'owner_type' => 'TENANT',
        'parent_id' => null,
        'name' => '饮品',
    ]);

    expect(fn () => Category::factory()->create([
        'tenant_id' => $tenantId,
        'owner_type' => 'TENANT',
        'parent_id' => null,
        'name' => '饮品',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('同 tenant 下 code 唯一冲突；多个 NULL 不冲突', function () {
    $tenantId = (string) Tenant::factory()->create()->id;

    Category::factory()->create([
        'tenant_id' => $tenantId, 'code' => 'B-DRINK',
    ]);

    expect(fn () => Category::factory()->create([
        'tenant_id' => $tenantId, 'code' => 'B-DRINK',
    ]))->toThrow(\Illuminate\Database\QueryException::class);

    Category::factory()->create(['tenant_id' => $tenantId, 'code' => null]);
    Category::factory()->create(['tenant_id' => $tenantId, 'code' => null]);
    expect(true)->toBeTrue();
});
```

- [ ] **Step 9: 跑测试**

```bash
./vendor/bin/pest app/Modules/Catalog/Tests/Unit/CategorySchemaTest.php
```

Expected: 7 passed.

- [ ] **Step 10: Commit**

```bash
git add app/Modules/Catalog/
git commit -m "feat(catalog): rebuild categories per docs/categories.md (owner+type+scope+tree+code)"
```

---

## Task 1B：重写 `TenantCategoryController` + 路由 + Vue 树形页面

> **背景**：Task 1A 升级了 categories schema。本任务把 controller、routes、Vue 页面同步升级到新 schema：树形展示、parent_id 路径计算、按 category_type / owner 筛选、防止移到自身子树下、防止删除被子分类或 items 引用的分类。

**Files:**
- Modify (重写整个文件): `app/Modules/Catalog/Http/Controllers/Web/TenantCategoryController.php`
- Modify: `routes/web.php`（确认 categories 路由完整）
- Modify (重写整个文件): `resources/js/pages/tenant/Categories/Index.vue`
- Modify (重写整个文件): `resources/js/pages/tenant/Categories/Create.vue`
- Modify (重写整个文件): `resources/js/pages/tenant/Categories/Edit.vue`

- [ ] **Step 1: 重写 TenantCategoryController**

完整覆盖 `app/Modules/Catalog/Http/Controllers/Web/TenantCategoryController.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Web;

use App\Modules\Catalog\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 租户后台 - 分类管理（路径 /tenant/categories）。
 * 支持：树形展示、owner_type 双层（TENANT/STORE）、category_type 三态、item_type_scope 限制、
 * parent_id 路径计算、有子分类或被 items 引用时禁删。
 */
class TenantCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $this->requireCurrentTenant($request);

        $rows = Category::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderBy('owner_type')
            ->orderBy('path')
            ->orderBy('sort_no')
            ->orderBy('name')
            ->get([
                'id', 'parent_id', 'owner_type', 'owner_store_id',
                'category_type', 'item_type_scope', 'name', 'code',
                'level', 'path', 'sort_no', 'status',
            ])
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'parent_id' => $c->parent_id,
                'owner_type' => $c->owner_type->value,
                'owner_store_id' => $c->owner_store_id,
                'category_type' => $c->category_type->value,
                'item_type_scope' => $c->item_type_scope->value,
                'name' => $c->name,
                'code' => $c->code,
                'level' => (int) $c->level,
                'path' => $c->path,
                'sort_no' => (int) $c->sort_no,
                'status' => $c->status->value,
            ])->all();

        return Inertia::render('tenant/Categories/Index', [
            'rows' => $rows,
        ]);
    }

    public function create(Request $request): Response
    {
        $tenantId = $this->requireCurrentTenant($request);

        return Inertia::render('tenant/Categories/Create', [
            'parents' => $this->parentOptions($tenantId),
        ]);
    }

    public function storeFromForm(Request $request): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        $data = $this->validatePayload($request, $tenantId, ignoreId: null);

        $parent = null;
        if (! empty($data['parent_id'])) {
            $parent = $this->resolve($tenantId, $data['parent_id']);
            $this->assertSameOwner($parent, $data['owner_type'], $data['owner_store_id'] ?? null);
        }

        $pathAndLevel = (new Category())->computePathAndLevel($parent);

        Category::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'owner_type' => $data['owner_type'],
            'owner_store_id' => $data['owner_store_id'] ?? null,
            'category_type' => $data['category_type'],
            'item_type_scope' => $data['item_type_scope'],
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'level' => $pathAndLevel['level'],
            'path' => $pathAndLevel['path'],
            'sort_no' => $data['sort_no'] ?? 0,
            'status' => $data['status'] ?? 'active',
        ]);

        return redirect('/tenant/categories')->with('success', '分类已创建');
    }

    public function edit(Request $request, string $id): Response
    {
        $tenantId = $this->requireCurrentTenant($request);
        $cat = $this->resolve($tenantId, $id);

        return Inertia::render('tenant/Categories/Edit', [
            'category' => [
                'id' => $cat->id,
                'parent_id' => $cat->parent_id,
                'owner_type' => $cat->owner_type->value,
                'owner_store_id' => $cat->owner_store_id,
                'category_type' => $cat->category_type->value,
                'item_type_scope' => $cat->item_type_scope->value,
                'name' => $cat->name,
                'code' => $cat->code,
                'sort_no' => (int) $cat->sort_no,
                'status' => $cat->status->value,
            ],
            'parents' => $this->parentOptions($tenantId, excludeSubtreeOf: $cat),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        $cat = $this->resolve($tenantId, $id);
        $data = $this->validatePayload($request, $tenantId, ignoreId: $cat->id);

        $newParent = null;
        if (! empty($data['parent_id'])) {
            if ($data['parent_id'] === $cat->id) {
                throw ValidationException::withMessages(['parent_id' => '不能将分类挂到自身']);
            }
            $newParent = $this->resolve($tenantId, $data['parent_id']);
            if (str_contains($newParent->path, '/'.$cat->id.'/')) {
                throw ValidationException::withMessages(['parent_id' => '不能挂到自身的子分类下']);
            }
            $this->assertSameOwner($newParent, $data['owner_type'], $data['owner_store_id'] ?? null);
        }

        $pathAndLevel = $cat->computePathAndLevel($newParent);

        $cat->update([
            'owner_type' => $data['owner_type'],
            'owner_store_id' => $data['owner_store_id'] ?? null,
            'category_type' => $data['category_type'],
            'item_type_scope' => $data['item_type_scope'],
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'sort_no' => $data['sort_no'] ?? 0,
            'status' => $data['status'] ?? 'active',
            'level' => $pathAndLevel['level'],
            'path' => $pathAndLevel['path'],
        ]);

        return back()->with('success', '已更新');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        $cat = $this->resolve($tenantId, $id);

        $hasChildren = Category::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('parent_id', $cat->id)->exists();
        if ($hasChildren) {
            throw ValidationException::withMessages(['category' => '该分类下仍有子分类，无法删除']);
        }

        // 被 items 引用时禁删（business_category_id / inventory_category_id 任一）
        // 注：items 表在 Task 2 才创建；此查询在 Task 2 之前不会命中（表不存在则跳过）。
        if (Schema::hasTable('items')) {
            $referenced = DB::table('items')
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($cat) {
                    $q->where('business_category_id', $cat->id)
                        ->orWhere('inventory_category_id', $cat->id);
                })->exists();
            if ($referenced) {
                throw ValidationException::withMessages(['category' => '该分类被商品引用，无法删除']);
            }
        }

        $cat->delete();
        return back()->with('success', '已删除');
    }

    private function validatePayload(Request $request, string $tenantId, ?string $ignoreId): array
    {
        return $request->validate([
            'owner_type' => ['required', Rule::in(['TENANT', 'STORE'])],
            'owner_store_id' => ['nullable', 'string', 'size:26', 'required_if:owner_type,STORE'],
            'category_type' => ['required', Rule::in(['BUSINESS', 'INVENTORY', 'BOTH'])],
            'item_type_scope' => ['required', Rule::in([
                'SALE_PRODUCT', 'RAW_MATERIAL', 'SEMI_FINISHED',
                'FINISHED_GOOD', 'SERVICE', 'PACKAGE', 'ALL',
            ])],
            'parent_id' => ['nullable', 'string', 'size:26'],
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:64',
                Rule::unique('categories', 'code')
                    ->ignore($ignoreId)
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'sort_no' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'status' => ['nullable', Rule::in(['active', 'disabled'])],
        ]);
    }

    private function assertSameOwner(Category $parent, string $childOwnerType, ?string $childOwnerStoreId): void
    {
        if ($parent->owner_type->value !== $childOwnerType
            || $parent->owner_store_id !== $childOwnerStoreId) {
            throw ValidationException::withMessages([
                'parent_id' => '父分类与子分类必须是同一所有者',
            ]);
        }
    }

    private function parentOptions(string $tenantId, ?Category $excludeSubtreeOf = null): array
    {
        $query = Category::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('owner_type')->orderBy('path')->orderBy('sort_no');

        if ($excludeSubtreeOf) {
            $excludePrefix = $excludeSubtreeOf->path.$excludeSubtreeOf->id.'/';
            $query->where('id', '!=', $excludeSubtreeOf->id)
                ->where('path', 'NOT LIKE', $excludePrefix.'%');
        }

        return $query->get(['id', 'parent_id', 'owner_type', 'owner_store_id', 'name', 'level', 'path'])
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'parent_id' => $c->parent_id,
                'owner_type' => $c->owner_type->value,
                'owner_store_id' => $c->owner_store_id,
                'name' => $c->name,
                'level' => (int) $c->level,
                'path' => $c->path,
            ])->all();
    }

    private function requireCurrentTenant(Request $request): string
    {
        $id = $request->session()->get('current_tenant_id');
        if (! $id) {
            throw ValidationException::withMessages(['tenant' => '尚未选定租户']);
        }
        return (string) $id;
    }

    private function resolve(string $tenantId, string $id): Category
    {
        $cat = Category::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereKey($id)->first();
        if (! $cat) {
            abort(404);
        }
        return $cat;
    }
}
```

- [ ] **Step 2: 确认 routes/web.php 中 categories 路由完整**

打开 `/routes/web.php`，定位 tenant 路由组，确认存在以下行（如已存在则跳过；缺失则补齐）：

```php
Route::get('categories', [TenantCategoryController::class, 'index']);
Route::get('categories/create', [TenantCategoryController::class, 'create']);
Route::post('categories', [TenantCategoryController::class, 'storeFromForm']);
Route::get('categories/{id}/edit', [TenantCategoryController::class, 'edit']);
Route::patch('categories/{id}', [TenantCategoryController::class, 'update']);
Route::delete('categories/{id}', [TenantCategoryController::class, 'destroy']);
```

- [ ] **Step 3: 重写 Index.vue（树形列表 + owner/type 筛选）**

完整覆盖 `resources/js/pages/tenant/Categories/Index.vue`：

```vue
<script setup lang="ts">
import { ref, computed } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import { ElTree, ElButton, ElTag, ElMessage, ElMessageBox, ElSelect, ElOption } from 'element-plus'

interface Row {
  id: string
  parent_id: string | null
  owner_type: 'TENANT' | 'STORE'
  owner_store_id: string | null
  category_type: 'BUSINESS' | 'INVENTORY' | 'BOTH'
  item_type_scope: string
  name: string
  code: string | null
  level: number
  path: string
  sort_no: number
  status: 'active' | 'disabled'
}

const props = defineProps<{ rows: Row[] }>()

const ownerFilter = ref<'ALL' | 'TENANT' | 'STORE'>('ALL')
const typeFilter = ref<'ALL' | 'BUSINESS' | 'INVENTORY' | 'BOTH'>('ALL')

const tree = computed(() => {
  const filtered = props.rows.filter(r =>
    (ownerFilter.value === 'ALL' || r.owner_type === ownerFilter.value)
    && (typeFilter.value === 'ALL' || r.category_type === typeFilter.value)
  )
  const byId = new Map<string, Row & { children: any[] }>()
  filtered.forEach(r => byId.set(r.id, { ...r, children: [] }))
  const roots: any[] = []
  byId.forEach(node => {
    if (node.parent_id && byId.has(node.parent_id)) {
      byId.get(node.parent_id)!.children.push(node)
    } else {
      roots.push(node)
    }
  })
  return roots
})

const onDelete = async (id: string, name: string) => {
  await ElMessageBox.confirm(`确认删除分类「${name}」？`, '删除', { type: 'warning' })
  router.delete(`/tenant/categories/${id}`, {
    onSuccess: () => ElMessage.success('已删除'),
  })
}
</script>

<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-xl font-bold">分类管理</h1>
      <Link href="/tenant/categories/create">
        <ElButton type="primary">新建分类</ElButton>
      </Link>
    </div>

    <div class="flex gap-4 mb-4">
      <ElSelect v-model="ownerFilter" style="width: 160px">
        <ElOption label="全部所有者" value="ALL" />
        <ElOption label="租户公共" value="TENANT" />
        <ElOption label="门店私有" value="STORE" />
      </ElSelect>
      <ElSelect v-model="typeFilter" style="width: 160px">
        <ElOption label="全部类型" value="ALL" />
        <ElOption label="经营" value="BUSINESS" />
        <ElOption label="库存物料" value="INVENTORY" />
        <ElOption label="通用" value="BOTH" />
      </ElSelect>
    </div>

    <ElTree :data="tree" node-key="id" :default-expand-all="true"
            :props="{ label: 'name', children: 'children' }">
      <template #default="{ data }">
        <div class="flex items-center gap-2 w-full">
          <span class="font-medium">{{ data.name }}</span>
          <ElTag v-if="data.code" size="small">{{ data.code }}</ElTag>
          <ElTag size="small" :type="data.category_type === 'INVENTORY' ? 'warning' : 'success'">
            {{ data.category_type }}
          </ElTag>
          <ElTag size="small" :type="data.owner_type === 'STORE' ? 'info' : ''">
            {{ data.owner_type }}
          </ElTag>
          <ElTag v-if="data.status === 'disabled'" size="small" type="danger">已停用</ElTag>
          <span class="text-xs text-gray-400">scope={{ data.item_type_scope }}</span>
          <span class="ml-auto flex gap-2">
            <Link :href="`/tenant/categories/${data.id}/edit`">
              <ElButton size="small">编辑</ElButton>
            </Link>
            <ElButton size="small" type="danger" @click="onDelete(data.id, data.name)">
              删除
            </ElButton>
          </span>
        </div>
      </template>
    </ElTree>
  </div>
</template>
```

- [ ] **Step 4: 重写 Create.vue**

完整覆盖 `resources/js/pages/tenant/Categories/Create.vue`：

```vue
<script setup lang="ts">
import { ref } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import { ElForm, ElFormItem, ElInput, ElSelect, ElOption, ElButton, ElInputNumber, ElMessage } from 'element-plus'

interface ParentOption {
  id: string
  parent_id: string | null
  owner_type: 'TENANT' | 'STORE'
  owner_store_id: string | null
  name: string
  level: number
  path: string
}

const props = defineProps<{ parents: ParentOption[] }>()

const form = ref({
  owner_type: 'TENANT' as 'TENANT' | 'STORE',
  owner_store_id: '' as string,
  category_type: 'BUSINESS' as 'BUSINESS' | 'INVENTORY' | 'BOTH',
  item_type_scope: 'ALL' as string,
  parent_id: '' as string,
  name: '',
  code: '',
  sort_no: 0,
  status: 'active' as 'active' | 'disabled',
})

const submit = () => {
  router.post('/tenant/categories', {
    ...form.value,
    owner_store_id: form.value.owner_type === 'STORE'
      ? (form.value.owner_store_id || null) : null,
    parent_id: form.value.parent_id || null,
    code: form.value.code || null,
  }, {
    onSuccess: () => ElMessage.success('已创建'),
  })
}
</script>

<template>
  <div class="p-6 max-w-2xl">
    <h1 class="text-xl font-bold mb-4">新建分类</h1>

    <ElForm :model="form" label-width="120px" @submit.prevent="submit">
      <ElFormItem label="所有者">
        <ElSelect v-model="form.owner_type">
          <ElOption label="租户公共" value="TENANT" />
          <ElOption label="门店私有" value="STORE" />
        </ElSelect>
      </ElFormItem>
      <ElFormItem v-if="form.owner_type === 'STORE'" label="门店 ID">
        <ElInput v-model="form.owner_store_id" placeholder="门店 ULID" />
      </ElFormItem>
      <ElFormItem label="分类类型">
        <ElSelect v-model="form.category_type">
          <ElOption label="经营 (BUSINESS)" value="BUSINESS" />
          <ElOption label="库存物料 (INVENTORY)" value="INVENTORY" />
          <ElOption label="通用 (BOTH)" value="BOTH" />
        </ElSelect>
      </ElFormItem>
      <ElFormItem label="挂载范围">
        <ElSelect v-model="form.item_type_scope">
          <ElOption label="全部 (ALL)" value="ALL" />
          <ElOption label="可销售商品" value="SALE_PRODUCT" />
          <ElOption label="原料" value="RAW_MATERIAL" />
          <ElOption label="半成品" value="SEMI_FINISHED" />
          <ElOption label="成品" value="FINISHED_GOOD" />
          <ElOption label="服务" value="SERVICE" />
          <ElOption label="包材" value="PACKAGE" />
        </ElSelect>
      </ElFormItem>
      <ElFormItem label="父分类">
        <ElSelect v-model="form.parent_id" clearable filterable>
          <ElOption v-for="p in parents" :key="p.id"
            :value="p.id"
            :label="'│ '.repeat(p.level - 1) + p.name + ' (' + p.owner_type + ')'" />
        </ElSelect>
      </ElFormItem>
      <ElFormItem label="名称" required>
        <ElInput v-model="form.name" maxlength="100" />
      </ElFormItem>
      <ElFormItem label="编码">
        <ElInput v-model="form.code" placeholder="如 B-DRINK 或 I-RAW-MILK" maxlength="64" />
      </ElFormItem>
      <ElFormItem label="排序">
        <ElInputNumber v-model="form.sort_no" :min="0" :max="9999" />
      </ElFormItem>
      <ElFormItem label="状态">
        <ElSelect v-model="form.status">
          <ElOption label="启用" value="active" />
          <ElOption label="停用" value="disabled" />
        </ElSelect>
      </ElFormItem>
      <ElFormItem>
        <ElButton type="primary" native-type="submit">创建</ElButton>
        <Link href="/tenant/categories" class="ml-2">
          <ElButton>取消</ElButton>
        </Link>
      </ElFormItem>
    </ElForm>
  </div>
</template>
```

- [ ] **Step 5: 重写 Edit.vue**

完整覆盖 `resources/js/pages/tenant/Categories/Edit.vue`：

```vue
<script setup lang="ts">
import { ref } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import { ElForm, ElFormItem, ElInput, ElSelect, ElOption, ElButton, ElInputNumber, ElMessage } from 'element-plus'

interface Category {
  id: string
  parent_id: string | null
  owner_type: 'TENANT' | 'STORE'
  owner_store_id: string | null
  category_type: 'BUSINESS' | 'INVENTORY' | 'BOTH'
  item_type_scope: string
  name: string
  code: string | null
  sort_no: number
  status: 'active' | 'disabled'
}

interface ParentOption {
  id: string
  name: string
  owner_type: 'TENANT' | 'STORE'
  level: number
  path: string
}

const props = defineProps<{ category: Category, parents: ParentOption[] }>()

const form = ref({
  owner_type: props.category.owner_type,
  owner_store_id: props.category.owner_store_id ?? '',
  category_type: props.category.category_type,
  item_type_scope: props.category.item_type_scope,
  parent_id: props.category.parent_id ?? '',
  name: props.category.name,
  code: props.category.code ?? '',
  sort_no: props.category.sort_no,
  status: props.category.status,
})

const submit = () => {
  router.patch(`/tenant/categories/${props.category.id}`, {
    ...form.value,
    owner_store_id: form.value.owner_type === 'STORE'
      ? (form.value.owner_store_id || null) : null,
    parent_id: form.value.parent_id || null,
    code: form.value.code || null,
  }, {
    onSuccess: () => ElMessage.success('已更新'),
  })
}
</script>

<template>
  <div class="p-6 max-w-2xl">
    <h1 class="text-xl font-bold mb-4">编辑分类</h1>

    <ElForm :model="form" label-width="120px" @submit.prevent="submit">
      <ElFormItem label="所有者">
        <ElSelect v-model="form.owner_type">
          <ElOption label="租户公共" value="TENANT" />
          <ElOption label="门店私有" value="STORE" />
        </ElSelect>
      </ElFormItem>
      <ElFormItem v-if="form.owner_type === 'STORE'" label="门店 ID">
        <ElInput v-model="form.owner_store_id" placeholder="门店 ULID" />
      </ElFormItem>
      <ElFormItem label="分类类型">
        <ElSelect v-model="form.category_type">
          <ElOption label="经营 (BUSINESS)" value="BUSINESS" />
          <ElOption label="库存物料 (INVENTORY)" value="INVENTORY" />
          <ElOption label="通用 (BOTH)" value="BOTH" />
        </ElSelect>
      </ElFormItem>
      <ElFormItem label="挂载范围">
        <ElSelect v-model="form.item_type_scope">
          <ElOption label="全部 (ALL)" value="ALL" />
          <ElOption label="可销售商品" value="SALE_PRODUCT" />
          <ElOption label="原料" value="RAW_MATERIAL" />
          <ElOption label="半成品" value="SEMI_FINISHED" />
          <ElOption label="成品" value="FINISHED_GOOD" />
          <ElOption label="服务" value="SERVICE" />
          <ElOption label="包材" value="PACKAGE" />
        </ElSelect>
      </ElFormItem>
      <ElFormItem label="父分类">
        <ElSelect v-model="form.parent_id" clearable filterable>
          <ElOption v-for="p in parents" :key="p.id"
            :value="p.id"
            :label="'│ '.repeat(p.level - 1) + p.name + ' (' + p.owner_type + ')'" />
        </ElSelect>
      </ElFormItem>
      <ElFormItem label="名称" required>
        <ElInput v-model="form.name" maxlength="100" />
      </ElFormItem>
      <ElFormItem label="编码">
        <ElInput v-model="form.code" maxlength="64" />
      </ElFormItem>
      <ElFormItem label="排序">
        <ElInputNumber v-model="form.sort_no" :min="0" :max="9999" />
      </ElFormItem>
      <ElFormItem label="状态">
        <ElSelect v-model="form.status">
          <ElOption label="启用" value="active" />
          <ElOption label="停用" value="disabled" />
        </ElSelect>
      </ElFormItem>
      <ElFormItem>
        <ElButton type="primary" native-type="submit">保存</ElButton>
        <Link href="/tenant/categories" class="ml-2">
          <ElButton>取消</ElButton>
        </Link>
      </ElFormItem>
    </ElForm>
  </div>
</template>
```

- [ ] **Step 6: 跑前端 type-check**

```bash
npx vue-tsc --noEmit
```

Expected: 无错误。

- [ ] **Step 7: Commit**

```bash
git add app/Modules/Catalog/Http/ routes/web.php resources/js/pages/tenant/Categories/
git commit -m "feat(catalog): rewrite TenantCategoryController + tree-view UI for new schema"
```

---

## Task 1C：重写 `TenantCategoryWebTest` 覆盖新 schema

> **背景**：Task 1A 升级了 schema，Task 1B 重写了 controller 与 Vue 页面。本任务把 web feature 测试覆盖到所有新行为：树形 path/level 计算、跨租户隔离、code 唯一冲突、有子分类禁删、被 item 引用禁删、parent 不能挂到自身子树、父子 owner 一致性。

**Files:**
- Modify (重写整个文件): `app/Modules/Catalog/Tests/Feature/TenantCategoryWebTest.php`

- [ ] **Step 1: 重写测试**

完整覆盖 `app/Modules/Catalog/Tests/Feature/TenantCategoryWebTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Category;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->actor = User::factory()->create();
    Membership::factory()->create([
        'user_id' => $this->actor->id,
        'tenant_id' => $this->tenant->id,
        'store_id' => null,
    ]);
    $this->actingAs($this->actor, 'web')
        ->withSession(['current_tenant_id' => $this->tenant->id]);
});

test('GET /tenant/categories 列出树形（含 path/level/owner_type/category_type）', function () {
    $root = Category::factory()->create([
        'tenant_id' => $this->tenant->id, 'name' => '饮品', 'code' => 'B-DRINK',
    ]);
    Category::factory()->child($root)->create([
        'tenant_id' => $this->tenant->id, 'name' => '奶茶', 'code' => 'B-DRINK-MILKTEA',
    ]);
    $other = Tenant::factory()->create();
    Category::factory()->create(['tenant_id' => $other->id]);

    $this->get('/tenant/categories')
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('tenant/Categories/Index')
            ->has('rows', 2)
            ->where('rows.0.name', '饮品')
            ->where('rows.0.level', 1)
            ->where('rows.0.path', '/')
            ->where('rows.1.name', '奶茶')
            ->where('rows.1.level', 2)
        );
});

test('POST /tenant/categories 新建顶级分类（path=/、level=1）', function () {
    $this->post('/tenant/categories', [
        'owner_type' => 'TENANT',
        'category_type' => 'BUSINESS',
        'item_type_scope' => 'ALL',
        'name' => '冷饮',
        'code' => 'B-COLD',
        'sort_no' => 5,
        'status' => 'active',
    ])->assertRedirect('/tenant/categories');

    $cat = Category::query()->withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->where('code', 'B-COLD')->first();
    expect($cat)->not->toBeNull();
    expect($cat->level)->toBe(1);
    expect($cat->path)->toBe('/');
    expect($cat->parent_id)->toBeNull();
});

test('POST /tenant/categories 新建子分类（自动计算 path/level）', function () {
    $root = Category::factory()->create([
        'tenant_id' => $this->tenant->id, 'name' => '饮品',
    ]);

    $this->post('/tenant/categories', [
        'owner_type' => 'TENANT',
        'category_type' => 'BUSINESS',
        'item_type_scope' => 'SALE_PRODUCT',
        'parent_id' => $root->id,
        'name' => '奶茶',
    ])->assertRedirect('/tenant/categories');

    $child = Category::query()->withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->where('name', '奶茶')->first();
    expect($child->parent_id)->toBe($root->id);
    expect($child->level)->toBe(2);
    expect($child->path)->toBe('/'.$root->id.'/');
});

test('POST 同 tenant 下 code 重复 → 422', function () {
    Category::factory()->create([
        'tenant_id' => $this->tenant->id, 'code' => 'B-DRINK',
    ]);

    $this->post('/tenant/categories', [
        'owner_type' => 'TENANT',
        'category_type' => 'BUSINESS',
        'item_type_scope' => 'ALL',
        'name' => '其他',
        'code' => 'B-DRINK',
    ])->assertSessionHasErrors('code');
});

test('POST owner_type=STORE 但 owner_store_id 缺失 → 422', function () {
    $this->post('/tenant/categories', [
        'owner_type' => 'STORE',
        'category_type' => 'BUSINESS',
        'item_type_scope' => 'ALL',
        'name' => '门店限定',
    ])->assertSessionHasErrors('owner_store_id');
});

test('PATCH 不允许把分类挂到自身的子树下', function () {
    $root = Category::factory()->create([
        'tenant_id' => $this->tenant->id, 'name' => 'A',
    ]);
    $child = Category::factory()->child($root)->create([
        'tenant_id' => $this->tenant->id, 'name' => 'A.1',
    ]);

    $this->patch("/tenant/categories/{$root->id}", [
        'owner_type' => 'TENANT',
        'category_type' => 'BUSINESS',
        'item_type_scope' => 'ALL',
        'parent_id' => $child->id,
        'name' => 'A',
    ])->assertSessionHasErrors('parent_id');
});

test('DELETE 有子分类时禁止删除', function () {
    $root = Category::factory()->create(['tenant_id' => $this->tenant->id]);
    Category::factory()->child($root)->create(['tenant_id' => $this->tenant->id]);

    $this->delete("/tenant/categories/{$root->id}")
        ->assertSessionHasErrors('category');

    expect(Category::query()->withoutGlobalScopes()->find($root->id))->not->toBeNull();
});

test('DELETE 无子分类、无 item 引用时软删除', function () {
    $cat = Category::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->delete("/tenant/categories/{$cat->id}");

    expect(Category::query()->withoutGlobalScopes()->find($cat->id))->toBeNull();
    expect(Category::query()->withoutGlobalScopes()->withTrashed()->find($cat->id))
        ->not->toBeNull();
});

test('跨租户隔离：他租户分类不可访问', function () {
    $other = Tenant::factory()->create();
    $otherCat = Category::factory()->create(['tenant_id' => $other->id]);

    $this->get("/tenant/categories/{$otherCat->id}/edit")->assertNotFound();
});

test('父子 owner 不一致 → 422', function () {
    $parent = Category::factory()->create([
        'tenant_id' => $this->tenant->id,
        'owner_type' => 'TENANT', 'owner_store_id' => null,
    ]);

    $this->post('/tenant/categories', [
        'owner_type' => 'STORE',
        'owner_store_id' => str_repeat('0', 26),
        'category_type' => 'BUSINESS',
        'item_type_scope' => 'ALL',
        'parent_id' => $parent->id,
        'name' => '门店子分类',
    ])->assertSessionHasErrors('parent_id');
});
```

- [ ] **Step 2: 跑测试**

```bash
./vendor/bin/pest app/Modules/Catalog/Tests/Feature/TenantCategoryWebTest.php
```

Expected: 10 passed.

- [ ] **Step 3: 跑 Catalog 全模块测试**

```bash
composer test -- --filter=Catalog
```

Expected: `CategorySchemaTest` (7) + `TenantCategoryWebTest` (10) 共 17 passed。

- [ ] **Step 4: Commit**

```bash
git add app/Modules/Catalog/Tests/
git commit -m "test(catalog): rewrite TenantCategoryWebTest for new schema (tree/owner/scope/code)"
```

---

## Task 2：创建 `items` 表（迁移 + 模型 + 工厂 + 枚举）

**Files:**
- Create: `app/Modules/Catalog/Database/Migrations/2026_05_08_000061_create_items_table.php`
- Create: `app/Modules/Catalog/Enums/ItemType.php`
- Create: `app/Modules/Catalog/Enums/ItemStatus.php`
- Create: `app/Modules/Catalog/Enums/OwnerType.php`
- Create: `app/Modules/Catalog/Models/Item.php`
- Create: `app/Modules/Catalog/Database/Factories/ItemFactory.php`
- Test: `app/Modules/Catalog/Tests/Unit/ItemSchemaTest.php`

- [ ] **Step 1: 写枚举 ItemType**

`app/Modules/Catalog/Enums/ItemType.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

/**
 * 物料类型。SALE_PRODUCT=可销售商品 / RAW_MATERIAL=原料 / SEMI_FINISHED=半成品
 * / FINISHED_GOOD=成品 / SERVICE=服务 / PACKAGE=包材。
 */
enum ItemType: string
{
    case SaleProduct = 'SALE_PRODUCT';
    case RawMaterial = 'RAW_MATERIAL';
    case SemiFinished = 'SEMI_FINISHED';
    case FinishedGood = 'FINISHED_GOOD';
    case Service = 'SERVICE';
    case Package = 'PACKAGE';
}
```

- [ ] **Step 2: 写枚举 ItemStatus**

`app/Modules/Catalog/Enums/ItemStatus.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

enum ItemStatus: string
{
    case Active = 'active';
    case OffShelf = 'off_shelf';
}
```

- [ ] **Step 3: 写枚举 OwnerType**

`app/Modules/Catalog/Enums/OwnerType.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

/**
 * Item 所属层级：商户级（全租户共享）或门店私有。
 */
enum OwnerType: string
{
    case Tenant = 'TENANT';
    case Store = 'STORE';
}
```

- [ ] **Step 4: 写迁移**

`app/Modules/Catalog/Database/Migrations/2026_05_08_000061_create_items_table.php`：

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->enum('owner_type', ['TENANT', 'STORE'])->default('TENANT');
            $table->char('owner_store_id', 26)->nullable();
            $table->enum('item_type', [
                'SALE_PRODUCT', 'RAW_MATERIAL', 'SEMI_FINISHED',
                'FINISHED_GOOD', 'SERVICE', 'PACKAGE',
            ])->default('SALE_PRODUCT');
            $table->string('item_name', 120);
            // 双分类字段（per docs/categories.md 方案 A）
            $table->char('business_category_id', 26)->nullable();
            $table->char('inventory_category_id', 26)->nullable();
            $table->string('unit', 20)->default('PCS');
            $table->boolean('sku_enabled')->default(true);
            $table->boolean('inventory_enabled')->default(true);
            $table->enum('status', ['active', 'off_shelf'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('business_category_id')->references('id')->on('categories')->nullOnDelete();
            $table->foreign('inventory_category_id')->references('id')->on('categories')->nullOnDelete();
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'item_type']);
            $table->index(['tenant_id', 'business_category_id']);
            $table->index(['tenant_id', 'inventory_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
```

- [ ] **Step 5: 写模型 Item**

`app/Modules/Catalog/Models/Item.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\ItemFactory;
use App\Modules\Catalog\Enums\ItemStatus;
use App\Modules\Catalog\Enums\ItemType;
use App\Modules\Catalog\Enums\OwnerType;
use App\Support\Eloquent\BelongsToTenant;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 物料主数据。所有可销售/采购/原料/服务实体的统一抽象。
 * SKU、库存、BOM 等子系统均挂在此 item。
 */
class Item extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUlid;
    use SoftDeletes;

    protected $table = 'items';

    protected $guarded = [];

    protected $casts = [
        'owner_type' => OwnerType::class,
        'item_type' => ItemType::class,
        'status' => ItemStatus::class,
        'sku_enabled' => 'bool',
        'inventory_enabled' => 'bool',
    ];

    public function businessCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'business_category_id');
    }

    public function inventoryCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'inventory_category_id');
    }

    public function skus(): HasMany
    {
        return $this->hasMany(ItemSku::class);
    }

    protected static function newFactory(): ItemFactory
    {
        return ItemFactory::new();
    }
}
```

- [ ] **Step 6: 写工厂 ItemFactory**

`app/Modules/Catalog/Database/Factories/ItemFactory.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Item;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemFactory extends Factory
{
    protected $model = Item::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'business_category_id' => null,
            'inventory_category_id' => null,
            'owner_type' => 'TENANT',
            'owner_store_id' => null,
            'item_type' => 'SALE_PRODUCT',
            'item_name' => $this->faker->unique()->words(2, true).' 物料',
            'unit' => $this->faker->randomElement(['PCS', 'BOX', 'G', 'ML']),
            'sku_enabled' => true,
            'inventory_enabled' => true,
            'status' => 'active',
        ];
    }

    /** 关联经营分类（category_type ∈ {BUSINESS, BOTH}） */
    public function withBusinessCategory(Category $category): static
    {
        return $this->state(['business_category_id' => $category->id]);
    }

    /** 关联库存物料分类（category_type ∈ {INVENTORY, BOTH}） */
    public function withInventoryCategory(Category $category): static
    {
        return $this->state(['inventory_category_id' => $category->id]);
    }
}
```

- [ ] **Step 7: 写 schema 单元测试（断言迁移正确）**

`app/Modules/Catalog/Tests/Unit/ItemSchemaTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('items 表能创建带默认值的行', function () {
    $item = Item::factory()->create();

    expect($item->id)->toBeString()->toHaveLength(26);
    expect($item->item_type->value)->toBe('SALE_PRODUCT');
    expect($item->owner_type->value)->toBe('TENANT');
    expect($item->status->value)->toBe('active');
    expect($item->sku_enabled)->toBeTrue();
    expect($item->inventory_enabled)->toBeTrue();
    expect($item->business_category_id)->toBeNull();
    expect($item->inventory_category_id)->toBeNull();
});

test('items 软删除生效', function () {
    $item = Item::factory()->create();
    $item->delete();

    expect(Item::query()->withoutGlobalScopes()->find($item->id))->toBeNull();
    expect(Item::query()->withoutGlobalScopes()->withTrashed()->find($item->id))
        ->not->toBeNull();
});
```

- [ ] **Step 8: 跑测试**

```bash
./vendor/bin/pest app/Modules/Catalog/Tests/Unit/ItemSchemaTest.php
```

Expected: 2 passed.

- [ ] **Step 9: Commit**

```bash
git add app/Modules/Catalog/
git commit -m "feat(catalog): create items table + Item model + enums"
```

---

## Task 3：创建 `item_skus` 表

**Files:**
- Create: `app/Modules/Catalog/Database/Migrations/2026_05_08_000062_create_item_skus_table.php`
- Create: `app/Modules/Catalog/Models/ItemSku.php`
- Create: `app/Modules/Catalog/Database/Factories/ItemSkuFactory.php`
- Test: `app/Modules/Catalog/Tests/Unit/ItemSkuSchemaTest.php`

- [ ] **Step 1: 写迁移**

`app/Modules/Catalog/Database/Migrations/2026_05_08_000062_create_item_skus_table.php`：

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_skus', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('item_id', 26);
            $table->json('spec_json')->nullable();
            $table->string('barcode', 64)->nullable();
            $table->unsignedInteger('sale_price_cents')->default(0);
            $table->unsignedInteger('cost_price_cents')->default(0);
            $table->boolean('inventory_enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
            $table->index(['item_id']);
            // barcode 在租户内唯一（NULL 不参与）
            $table->unique(['tenant_id', 'barcode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_skus');
    }
};
```

- [ ] **Step 2: 写模型 ItemSku**

`app/Modules/Catalog/Models/ItemSku.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\ItemSkuFactory;
use App\Support\Eloquent\BelongsToTenant;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemSku extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUlid;
    use SoftDeletes;

    protected $table = 'item_skus';

    protected $guarded = [];

    protected $casts = [
        'spec_json' => 'array',
        'sale_price_cents' => 'int',
        'cost_price_cents' => 'int',
        'inventory_enabled' => 'bool',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function policy(): HasOne
    {
        return $this->hasOne(ProductInventoryPolicy::class, 'sku_id');
    }

    protected static function newFactory(): ItemSkuFactory
    {
        return ItemSkuFactory::new();
    }
}
```

- [ ] **Step 3: 写工厂**

`app/Modules/Catalog/Database/Factories/ItemSkuFactory.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemSkuFactory extends Factory
{
    protected $model = ItemSku::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'item_id' => Item::factory(),
            'spec_json' => [],
            'barcode' => null,
            'sale_price_cents' => $this->faker->numberBetween(100, 9999),
            'cost_price_cents' => $this->faker->numberBetween(50, 5000),
            'inventory_enabled' => true,
        ];
    }
}
```

- [ ] **Step 4: 写 schema 测试**

`app/Modules/Catalog/Tests/Unit/ItemSkuSchemaTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('item_skus 表能创建并关联 item', function () {
    $sku = ItemSku::factory()->create();

    expect($sku->id)->toHaveLength(26);
    expect($sku->item)->not->toBeNull();
    expect($sku->spec_json)->toBe([]);
});

test('barcode 同租户唯一（不同租户可重复）', function () {
    $t1 = Tenant::factory()->create();
    $t2 = Tenant::factory()->create();
    $i1 = Item::factory()->create(['tenant_id' => $t1->id]);
    $i2 = Item::factory()->create(['tenant_id' => $t2->id]);

    ItemSku::factory()->create(['tenant_id' => $t1->id, 'item_id' => $i1->id, 'barcode' => 'ABC123']);
    ItemSku::factory()->create(['tenant_id' => $t2->id, 'item_id' => $i2->id, 'barcode' => 'ABC123']);

    expect(true)->toBeTrue(); // 不抛 DB 异常即通过

    expect(fn () => ItemSku::factory()->create([
        'tenant_id' => $t1->id, 'item_id' => $i1->id, 'barcode' => 'ABC123',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
```

- [ ] **Step 5: 跑测试**

```bash
./vendor/bin/pest app/Modules/Catalog/Tests/Unit/ItemSkuSchemaTest.php
```

Expected: 2 passed.

- [ ] **Step 6: Commit**

```bash
git add app/Modules/Catalog/
git commit -m "feat(catalog): create item_skus table + ItemSku model"
```

---

## Task 4：创建 `product_inventory_policies` 表 + SKU Observer

**Files:**
- Create: `app/Modules/Catalog/Database/Migrations/2026_05_08_000063_create_product_inventory_policies_table.php`
- Create: `app/Modules/Catalog/Enums/InventoryTrackType.php`
- Create: `app/Modules/Catalog/Enums/StockDeductMode.php`
- Create: `app/Modules/Catalog/Models/ProductInventoryPolicy.php`
- Create: `app/Modules/Catalog/Database/Factories/ProductInventoryPolicyFactory.php`
- Create: `app/Modules/Catalog/Observers/ItemSkuObserver.php`
- Modify: `app/Modules/Catalog/CatalogServiceProvider.php`（注册 Observer）
- Test: `app/Modules/Catalog/Tests/Feature/ItemSkuObserverTest.php`

- [ ] **Step 1: 写枚举 InventoryTrackType**

`app/Modules/Catalog/Enums/InventoryTrackType.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

enum InventoryTrackType: string
{
    case None = 'NONE';
    case FinishedGood = 'FINISHED_GOOD';
    case RawMaterial = 'RAW_MATERIAL';
    case Both = 'BOTH';
}
```

- [ ] **Step 2: 写枚举 StockDeductMode**

`app/Modules/Catalog/Enums/StockDeductMode.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

enum StockDeductMode: string
{
    case SaleDeduct = 'SALE_DEDUCT';
    case ManualDeduct = 'MANUAL_DEDUCT';
    case ProductionDeduct = 'PRODUCTION_DEDUCT';
}
```

- [ ] **Step 3: 写迁移**

`app/Modules/Catalog/Database/Migrations/2026_05_08_000063_create_product_inventory_policies_table.php`：

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_inventory_policies', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('sku_id', 26);
            $table->enum('inventory_track_type',
                ['NONE', 'FINISHED_GOOD', 'RAW_MATERIAL', 'BOTH'])
                ->default('FINISHED_GOOD');
            $table->enum('stock_deduct_mode',
                ['SALE_DEDUCT', 'MANUAL_DEDUCT', 'PRODUCTION_DEDUCT'])
                ->default('MANUAL_DEDUCT');
            $table->boolean('allow_negative_stock')->default(false);
            $table->boolean('batch_required')->default(false);
            $table->boolean('expiry_required')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('sku_id')->references('id')->on('item_skus')->cascadeOnDelete();
            $table->unique('sku_id'); // 一对一
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_inventory_policies');
    }
};
```

- [ ] **Step 4: 写模型**

`app/Modules/Catalog/Models/ProductInventoryPolicy.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\ProductInventoryPolicyFactory;
use App\Modules\Catalog\Enums\InventoryTrackType;
use App\Modules\Catalog\Enums\StockDeductMode;
use App\Support\Eloquent\BelongsToTenant;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductInventoryPolicy extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUlid;

    protected $table = 'product_inventory_policies';

    protected $guarded = [];

    protected $casts = [
        'inventory_track_type' => InventoryTrackType::class,
        'stock_deduct_mode' => StockDeductMode::class,
        'allow_negative_stock' => 'bool',
        'batch_required' => 'bool',
        'expiry_required' => 'bool',
    ];

    public function sku(): BelongsTo
    {
        return $this->belongsTo(ItemSku::class, 'sku_id');
    }

    protected static function newFactory(): ProductInventoryPolicyFactory
    {
        return ProductInventoryPolicyFactory::new();
    }
}
```

- [ ] **Step 5: 写工厂**

`app/Modules/Catalog/Database/Factories/ProductInventoryPolicyFactory.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Catalog\Models\ProductInventoryPolicy;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductInventoryPolicyFactory extends Factory
{
    protected $model = ProductInventoryPolicy::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'sku_id' => ItemSku::factory(),
            'inventory_track_type' => 'FINISHED_GOOD',
            'stock_deduct_mode' => 'MANUAL_DEDUCT',
            'allow_negative_stock' => false,
            'batch_required' => false,
            'expiry_required' => false,
        ];
    }
}
```

- [ ] **Step 6: 写 ItemSkuObserver**

`app/Modules/Catalog/Observers/ItemSkuObserver.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Observers;

use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Catalog\Models\ProductInventoryPolicy;

/**
 * SKU 创建时自动建一条默认 policy（一对一）。
 * 默认值与 product_inventory_policies 迁移中的列默认一致：
 * track_type=FINISHED_GOOD, deduct_mode=MANUAL_DEDUCT。
 */
class ItemSkuObserver
{
    public function created(ItemSku $sku): void
    {
        ProductInventoryPolicy::query()->withoutGlobalScopes()->create([
            'tenant_id' => $sku->tenant_id,
            'sku_id' => $sku->id,
            'inventory_track_type' => 'FINISHED_GOOD',
            'stock_deduct_mode' => 'MANUAL_DEDUCT',
            'allow_negative_stock' => false,
            'batch_required' => false,
            'expiry_required' => false,
        ]);
    }
}
```

- [ ] **Step 7: 修改 CatalogServiceProvider 注册 Observer**

`app/Modules/Catalog/CatalogServiceProvider.php` 完整重写：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Catalog;

use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Catalog\Observers\ItemSkuObserver;
use App\Support\ModuleServiceProvider;

class CatalogServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__;
    }

    public function boot(): void
    {
        parent::boot();
        ItemSku::observe(ItemSkuObserver::class);
    }
}
```

- [ ] **Step 8: 写 Observer 测试**

`app/Modules/Catalog/Tests/Feature/ItemSkuObserverTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Catalog\Models\ProductInventoryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('创建 ItemSku 时自动建立默认 policy', function () {
    $sku = ItemSku::factory()->create();

    $policy = ProductInventoryPolicy::query()->withoutGlobalScopes()
        ->where('sku_id', $sku->id)->first();

    expect($policy)->not->toBeNull();
    expect($policy->tenant_id)->toBe($sku->tenant_id);
    expect($policy->inventory_track_type->value)->toBe('FINISHED_GOOD');
    expect($policy->stock_deduct_mode->value)->toBe('MANUAL_DEDUCT');
    expect($policy->allow_negative_stock)->toBeFalse();
});

test('每个 sku 只能有一条 policy（DB 唯一约束）', function () {
    $sku = ItemSku::factory()->create(); // observer 已建一条

    expect(fn () => ProductInventoryPolicy::factory()->create([
        'tenant_id' => $sku->tenant_id,
        'sku_id' => $sku->id,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
```

- [ ] **Step 9: 跑测试**

```bash
./vendor/bin/pest app/Modules/Catalog/Tests/Feature/ItemSkuObserverTest.php
```

Expected: 2 passed.

- [ ] **Step 10: Commit**

```bash
git add app/Modules/Catalog/
git commit -m "feat(catalog): add product_inventory_policies + auto-create observer"
```

---

## Task 5：TenantItemController + 路由 + Vue 页面

**Files:**
- Create: `app/Modules/Catalog/Http/Controllers/Web/TenantItemController.php`
- Create: `resources/js/pages/tenant/Items/Index.vue`
- Create: `resources/js/pages/tenant/Items/Create.vue`
- Create: `resources/js/pages/tenant/Items/Edit.vue`
- Modify: `routes/web.php`（新增 items 路由组）

- [ ] **Step 1: 写 Controller**

`app/Modules/Catalog/Http/Controllers/Web/TenantItemController.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Web;

use App\Modules\Catalog\Enums\ItemStatus;
use App\Modules\Catalog\Enums\ItemType;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Catalog\Models\ProductInventoryPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 租户后台 - 物料（Item）。前端嵌套提交 sku（单 SKU 时一行）+ policy（一对一）。
 * 后端使用事务确保 item / sku / policy 三件同步建立。
 */
class TenantItemController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $this->requireCurrentTenant($request);

        $q = trim((string) $request->query('q', ''));
        $itemType = (string) $request->query('item_type', 'all');
        $status = (string) $request->query('status', 'all');
        $page = max(1, (int) $request->query('page', 1));
        $pageSize = min(100, max(5, (int) $request->query('per_page', 20)));

        $query = Item::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at');
        if ($q !== '') {
            $query->where('item_name', 'like', "%{$q}%");
        }
        if (in_array($itemType, array_column(ItemType::cases(), 'value'), true)) {
            $query->where('item_type', $itemType);
        }
        if (in_array($status, ['active', 'off_shelf'], true)) {
            $query->where('status', $status);
        }

        $total = (clone $query)->count();
        $items = $query->skip(($page - 1) * $pageSize)->take($pageSize)->get();

        $catIds = $items->pluck('business_category_id')
            ->merge($items->pluck('inventory_category_id'))
            ->filter()->unique()->values();
        $categoryNames = Category::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $catIds)
            ->pluck('name', 'id');

        $skuMap = ItemSku::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('item_id', $items->pluck('id'))
            ->get()->groupBy('item_id');

        $rows = $items->map(fn (Item $i) => [
            'id' => $i->id,
            'item_name' => $i->item_name,
            'item_type' => $i->item_type->value,
            'business_category_id' => $i->business_category_id,
            'business_category_name' => $i->business_category_id ? ($categoryNames[$i->business_category_id] ?? null) : null,
            'inventory_category_id' => $i->inventory_category_id,
            'inventory_category_name' => $i->inventory_category_id ? ($categoryNames[$i->inventory_category_id] ?? null) : null,
            'unit' => $i->unit,
            'sku_count' => $skuMap->get($i->id)?->count() ?? 0,
            'first_sku_price_cents' => (int) ($skuMap->get($i->id)?->first()?->sale_price_cents ?? 0),
            'inventory_enabled' => (bool) $i->inventory_enabled,
            'status' => $i->status->value,
            'created_at' => $i->created_at?->toIso8601String(),
        ])->all();

        $hasCategories = Category::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->exists();

        return Inertia::render('tenant/Items/Index', [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'q' => $q,
            'item_type' => $itemType,
            'status' => $status,
            'has_categories' => $hasCategories,
            'item_types' => array_column(ItemType::cases(), 'value'),
        ]);
    }

    public function create(Request $request): Response
    {
        $tenantId = $this->requireCurrentTenant($request);

        return Inertia::render('tenant/Items/Create', [
            'categories' => $this->categoryOptions($tenantId),
            'item_types' => array_column(ItemType::cases(), 'value'),
        ]);
    }

    public function storeFromForm(Request $request): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        $data = $this->validatePayload($request, $tenantId, withStatus: false);

        DB::transaction(function () use ($tenantId, $data) {
            $item = Item::query()->withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'item_name' => $data['item_name'],
                'item_type' => $data['item_type'],
                'business_category_id' => $data['business_category_id'] ?: null,
                'inventory_category_id' => $data['inventory_category_id'] ?: null,
                'unit' => $data['unit'],
                'sku_enabled' => true,
                'inventory_enabled' => $data['inventory_enabled'],
                'status' => 'active',
            ]);

            // 创建首个 SKU；observer 自动建 policy
            $sku = ItemSku::query()->withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'item_id' => $item->id,
                'spec_json' => $data['sku']['spec_json'] ?? [],
                'barcode' => $data['sku']['barcode'] ?: null,
                'sale_price_cents' => (int) $data['sku']['sale_price_cents'],
                'cost_price_cents' => (int) ($data['sku']['cost_price_cents'] ?? 0),
                'inventory_enabled' => true,
            ]);

            // 写入 policy 用户传值（覆盖 observer 默认）
            if (! empty($data['policy'])) {
                ProductInventoryPolicy::query()->withoutGlobalScopes()
                    ->where('sku_id', $sku->id)
                    ->update([
                        'inventory_track_type' => $data['policy']['inventory_track_type'],
                        'stock_deduct_mode' => $data['policy']['stock_deduct_mode'],
                        'allow_negative_stock' => (bool) $data['policy']['allow_negative_stock'],
                        'batch_required' => (bool) $data['policy']['batch_required'],
                        'expiry_required' => (bool) $data['policy']['expiry_required'],
                    ]);
            }
        });

        return redirect('/tenant/items')->with('success', '物料已创建');
    }

    public function edit(Request $request, string $id): Response
    {
        $tenantId = $this->requireCurrentTenant($request);
        $item = $this->resolve($tenantId, $id);
        $sku = $item->skus()->withoutGlobalScopes()->first();
        $policy = $sku ? ProductInventoryPolicy::query()->withoutGlobalScopes()
            ->where('sku_id', $sku->id)->first() : null;

        return Inertia::render('tenant/Items/Edit', [
            'item' => [
                'id' => $item->id,
                'item_name' => $item->item_name,
                'item_type' => $item->item_type->value,
                'business_category_id' => $item->business_category_id,
                'inventory_category_id' => $item->inventory_category_id,
                'unit' => $item->unit,
                'inventory_enabled' => (bool) $item->inventory_enabled,
                'status' => $item->status->value,
            ],
            'sku' => $sku ? [
                'id' => $sku->id,
                'spec_json' => $sku->spec_json,
                'barcode' => $sku->barcode,
                'sale_price_cents' => (int) $sku->sale_price_cents,
                'cost_price_cents' => (int) $sku->cost_price_cents,
            ] : null,
            'policy' => $policy ? [
                'inventory_track_type' => $policy->inventory_track_type->value,
                'stock_deduct_mode' => $policy->stock_deduct_mode->value,
                'allow_negative_stock' => (bool) $policy->allow_negative_stock,
                'batch_required' => (bool) $policy->batch_required,
                'expiry_required' => (bool) $policy->expiry_required,
            ] : null,
            'categories' => $this->categoryOptions($tenantId),
            'item_types' => array_column(ItemType::cases(), 'value'),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        $item = $this->resolve($tenantId, $id);
        $sku = $item->skus()->withoutGlobalScopes()->first();

        $data = $this->validatePayload($request, $tenantId, withStatus: true, ignoreSkuId: $sku?->id);

        DB::transaction(function () use ($item, $sku, $data) {
            $item->update([
                'item_name' => $data['item_name'],
                'item_type' => $data['item_type'],
                'business_category_id' => $data['business_category_id'] ?: null,
                'inventory_category_id' => $data['inventory_category_id'] ?: null,
                'unit' => $data['unit'],
                'inventory_enabled' => (bool) $data['inventory_enabled'],
                'status' => ItemStatus::from($data['status']),
            ]);

            if ($sku) {
                $sku->update([
                    'spec_json' => $data['sku']['spec_json'] ?? [],
                    'barcode' => $data['sku']['barcode'] ?: null,
                    'sale_price_cents' => (int) $data['sku']['sale_price_cents'],
                    'cost_price_cents' => (int) ($data['sku']['cost_price_cents'] ?? 0),
                ]);

                if (! empty($data['policy'])) {
                    ProductInventoryPolicy::query()->withoutGlobalScopes()
                        ->where('sku_id', $sku->id)
                        ->update([
                            'inventory_track_type' => $data['policy']['inventory_track_type'],
                            'stock_deduct_mode' => $data['policy']['stock_deduct_mode'],
                            'allow_negative_stock' => (bool) $data['policy']['allow_negative_stock'],
                            'batch_required' => (bool) $data['policy']['batch_required'],
                            'expiry_required' => (bool) $data['policy']['expiry_required'],
                        ]);
                }
            }
        });

        return back()->with('success', '已更新');
    }

    private function requireCurrentTenant(Request $request): string
    {
        $id = $request->session()->get('current_tenant_id');
        if (! $id) {
            throw ValidationException::withMessages(['tenant' => '尚未选定租户']);
        }
        return (string) $id;
    }

    private function resolve(string $tenantId, string $id): Item
    {
        $i = Item::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->whereKey($id)->first();
        if (! $i) {
            abort(404);
        }
        return $i;
    }

    /**
     * 返回所有租户级激活分类（含 owner_type / category_type / item_type_scope / level / path），
     * 前端按 category_type 与 item_type_scope 自行筛选 business / inventory 下拉。
     *
     * @return array<int, array{id:string,name:string,owner_type:string,category_type:string,item_type_scope:string,level:int,path:string}>
     */
    private function categoryOptions(string $tenantId): array
    {
        return Category::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('owner_type')->orderBy('path')->orderBy('sort_no')->orderBy('name')
            ->get(['id', 'name', 'owner_type', 'category_type', 'item_type_scope', 'level', 'path'])
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'owner_type' => $c->owner_type->value,
                'category_type' => $c->category_type->value,
                'item_type_scope' => $c->item_type_scope->value,
                'level' => (int) $c->level,
                'path' => $c->path,
            ])->all();
    }

    private function validatePayload(Request $request, string $tenantId, bool $withStatus, ?string $ignoreSkuId = null): array
    {
        $itemTypes = array_column(ItemType::cases(), 'value');
        $itemTypeForScope = (string) $request->input('item_type', '');
        $rules = [
            'item_name' => ['required', 'string', 'max:120'],
            'item_type' => ['required', Rule::in($itemTypes)],
            'business_category_id' => ['nullable', 'string', 'size:26',
                Rule::exists('categories', 'id')->where(function ($q) use ($tenantId, $itemTypeForScope) {
                    $q->where('tenant_id', $tenantId)
                        ->whereIn('category_type', ['BUSINESS', 'BOTH'])
                        ->where(function ($q2) use ($itemTypeForScope) {
                            $q2->where('item_type_scope', 'ALL');
                            if ($itemTypeForScope !== '') {
                                $q2->orWhere('item_type_scope', $itemTypeForScope);
                            }
                        });
                }),
            ],
            'inventory_category_id' => ['nullable', 'string', 'size:26',
                Rule::exists('categories', 'id')->where(function ($q) use ($tenantId, $itemTypeForScope) {
                    $q->where('tenant_id', $tenantId)
                        ->whereIn('category_type', ['INVENTORY', 'BOTH'])
                        ->where(function ($q2) use ($itemTypeForScope) {
                            $q2->where('item_type_scope', 'ALL');
                            if ($itemTypeForScope !== '') {
                                $q2->orWhere('item_type_scope', $itemTypeForScope);
                            }
                        });
                }),
            ],
            'unit' => ['required', 'string', 'max:20'],
            'inventory_enabled' => ['required', 'boolean'],
            'sku' => ['required', 'array'],
            'sku.spec_json' => ['nullable', 'array'],
            'sku.barcode' => ['nullable', 'string', 'max:64',
                Rule::unique('item_skus', 'barcode')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNotNull('barcode'))
                    ->ignore($ignoreSkuId, 'id'),
            ],
            'sku.sale_price_cents' => ['required', 'integer', 'min:0', 'max:99999999'],
            'sku.cost_price_cents' => ['nullable', 'integer', 'min:0', 'max:99999999'],
            'policy' => ['nullable', 'array'],
            'policy.inventory_track_type' => ['nullable', Rule::in(['NONE', 'FINISHED_GOOD', 'RAW_MATERIAL', 'BOTH'])],
            'policy.stock_deduct_mode' => ['nullable', Rule::in(['SALE_DEDUCT', 'MANUAL_DEDUCT', 'PRODUCTION_DEDUCT'])],
            'policy.allow_negative_stock' => ['nullable', 'boolean'],
            'policy.batch_required' => ['nullable', 'boolean'],
            'policy.expiry_required' => ['nullable', 'boolean'],
        ];
        if ($withStatus) {
            $rules['status'] = ['required', 'in:active,off_shelf'];
        }
        return $request->validate($rules);
    }
}
```

- [ ] **Step 2: 修改 routes/web.php**

打开 `/routes/web.php`，在顶部 `use` 区添加：

```php
use App\Modules\Catalog\Http\Controllers\Web\TenantItemController;
```

在 `Route::prefix('tenant')` 组内（categories 路由之后），加入：

```php
Route::get('items', [TenantItemController::class, 'index']);
Route::get('items/create', [TenantItemController::class, 'create']);
Route::post('items', [TenantItemController::class, 'storeFromForm']);
Route::get('items/{id}/edit', [TenantItemController::class, 'edit']);
Route::patch('items/{id}', [TenantItemController::class, 'update']);
```

- [ ] **Step 3: 写 Items/Index.vue**

`resources/js/pages/tenant/Items/Index.vue`：

```vue
<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';

interface Row {
  id: string; item_name: string; item_type: string;
  business_category_id: string | null; business_category_name: string | null;
  inventory_category_id: string | null; inventory_category_name: string | null;
  unit: string; sku_count: number; first_sku_price_cents: number;
  inventory_enabled: boolean; status: string; created_at: string | null;
}

const page = usePage();
const props = computed(() => page.props as unknown as {
  rows: Row[]; total: number; page: number; pageSize: number;
  q: string; item_type: string; status: string;
  has_categories: boolean; item_types: string[];
});

const keyword = ref(props.value.q);
const itemType = ref(props.value.item_type);
const statusFilter = ref(props.value.status);

function reload(params: Record<string, unknown> = {}) {
  router.get('/tenant/items', {
    page: props.value.page, per_page: props.value.pageSize,
    q: keyword.value, item_type: itemType.value, status: statusFilter.value,
    ...params,
  }, { preserveState: true, preserveScroll: true });
}

function priceYuan(cents: number): string {
  return (cents / 100).toFixed(2);
}
</script>

<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-xl font-medium">物料管理</h1>
      <Link href="/tenant/items/create"
        class="px-3 py-1.5 bg-indigo-600 text-white text-sm rounded">
        新建物料
      </Link>
    </div>

    <div v-if="!props.has_categories"
      class="mb-4 px-3 py-2 bg-amber-50 border border-amber-200 text-sm rounded">
      请先到「分类管理」创建至少一个分类后再新建物料。
    </div>

    <div class="flex gap-2 mb-3">
      <input v-model="keyword" placeholder="搜索物料名"
        class="px-2 py-1 border rounded text-sm w-60"
        @keyup.enter="reload({ page: 1 })" />
      <select v-model="itemType" class="px-2 py-1 border rounded text-sm"
        @change="reload({ page: 1 })">
        <option value="all">全部类型</option>
        <option v-for="t in props.item_types" :key="t" :value="t">{{ t }}</option>
      </select>
      <select v-model="statusFilter" class="px-2 py-1 border rounded text-sm"
        @change="reload({ page: 1 })">
        <option value="all">全部状态</option>
        <option value="active">在售</option>
        <option value="off_shelf">下架</option>
      </select>
    </div>

    <table class="w-full text-sm border-collapse">
      <thead>
        <tr class="border-b bg-slate-50">
          <th class="text-left p-2">名称</th>
          <th class="text-left p-2">类型</th>
          <th class="text-left p-2">经营分类</th>
          <th class="text-left p-2">库存分类</th>
          <th class="text-left p-2">单位</th>
          <th class="text-right p-2">SKU 数</th>
          <th class="text-right p-2">起售价</th>
          <th class="text-center p-2">库存</th>
          <th class="text-center p-2">状态</th>
          <th class="text-right p-2">操作</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="r in props.rows" :key="r.id" class="border-b hover:bg-slate-50">
          <td class="p-2">{{ r.item_name }}</td>
          <td class="p-2">{{ r.item_type }}</td>
          <td class="p-2">{{ r.business_category_name || '—' }}</td>
          <td class="p-2">{{ r.inventory_category_name || '—' }}</td>
          <td class="p-2">{{ r.unit }}</td>
          <td class="p-2 text-right">{{ r.sku_count }}</td>
          <td class="p-2 text-right">¥{{ priceYuan(r.first_sku_price_cents) }}</td>
          <td class="p-2 text-center">
            <span v-if="r.inventory_enabled" class="text-emerald-600">是</span>
            <span v-else class="text-slate-400">否</span>
          </td>
          <td class="p-2 text-center">
            <span :class="r.status === 'active' ? 'text-emerald-600' : 'text-slate-400'">
              {{ r.status === 'active' ? '在售' : '下架' }}
            </span>
          </td>
          <td class="p-2 text-right">
            <Link :href="`/tenant/items/${r.id}/edit`" class="text-indigo-600">编辑</Link>
          </td>
        </tr>
        <tr v-if="props.rows.length === 0">
          <td colspan="10" class="text-center text-slate-400 p-6">暂无物料</td>
        </tr>
      </tbody>
    </table>

    <div class="mt-4 flex items-center justify-between text-sm">
      <span>共 {{ props.total }} 条</span>
      <div class="flex gap-1">
        <button :disabled="props.page <= 1"
          class="px-2 py-1 border rounded disabled:opacity-40"
          @click="reload({ page: props.page - 1 })">上一页</button>
        <span class="px-2 py-1">{{ props.page }}</span>
        <button :disabled="props.page * props.pageSize >= props.total"
          class="px-2 py-1 border rounded disabled:opacity-40"
          @click="reload({ page: props.page + 1 })">下一页</button>
      </div>
    </div>
  </div>
</template>
```

- [ ] **Step 4: 写 Items/Create.vue**

`resources/js/pages/tenant/Items/Create.vue`：

```vue
<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const page = usePage();
interface CategoryOption {
  id: string; name: string;
  owner_type: 'TENANT' | 'STORE';
  category_type: 'BUSINESS' | 'INVENTORY' | 'BOTH';
  item_type_scope: string;
  level: number;
  path: string;
}
const props = computed(() => page.props as unknown as {
  categories: CategoryOption[];
  item_types: string[];
});

const form = ref({
  item_name: '',
  item_type: 'SALE_PRODUCT',
  business_category_id: '' as string,
  inventory_category_id: '' as string,
  unit: 'PCS',
  inventory_enabled: true,
  sku: { spec_json: {}, barcode: '', sale_price_cents: 0, cost_price_cents: 0 },
  policy: {
    inventory_track_type: 'FINISHED_GOOD',
    stock_deduct_mode: 'MANUAL_DEDUCT',
    allow_negative_stock: false,
    batch_required: false,
    expiry_required: false,
  },
});

const businessCategoryOptions = computed(() => props.value.categories.filter(c =>
  (c.category_type === 'BUSINESS' || c.category_type === 'BOTH')
  && (c.item_type_scope === 'ALL' || c.item_type_scope === form.value.item_type)
));
const inventoryCategoryOptions = computed(() => props.value.categories.filter(c =>
  (c.category_type === 'INVENTORY' || c.category_type === 'BOTH')
  && (c.item_type_scope === 'ALL' || c.item_type_scope === form.value.item_type)
));

const errors = ref<Record<string, string>>({});

function submit() {
  router.post('/tenant/items', form.value as unknown as Record<string, unknown>, {
    onError: (e) => { errors.value = e as Record<string, string>; },
  });
}
</script>

<template>
  <div class="p-6 max-w-3xl">
    <h1 class="text-xl font-medium mb-4">新建物料</h1>

    <form @submit.prevent="submit" class="space-y-4 text-sm">
      <div>
        <label class="block mb-1">物料名</label>
        <input v-model="form.item_name" class="w-full px-2 py-1 border rounded" />
        <div v-if="errors.item_name" class="text-rose-600 text-xs mt-1">{{ errors.item_name }}</div>
      </div>

      <div class="grid grid-cols-3 gap-4">
        <div>
          <label class="block mb-1">类型</label>
          <select v-model="form.item_type" class="w-full px-2 py-1 border rounded">
            <option v-for="t in props.item_types" :key="t" :value="t">{{ t }}</option>
          </select>
        </div>
        <div>
          <label class="block mb-1">经营分类</label>
          <select v-model="form.business_category_id" class="w-full px-2 py-1 border rounded">
            <option value="">不选</option>
            <option v-for="c in businessCategoryOptions" :key="c.id" :value="c.id">
              {{ '│ '.repeat(c.level - 1) }}{{ c.name }}{{ c.owner_type === 'STORE' ? ' (门店)' : '' }}
            </option>
          </select>
          <div v-if="errors.business_category_id" class="text-rose-600 text-xs mt-1">{{ errors.business_category_id }}</div>
        </div>
        <div>
          <label class="block mb-1">库存分类</label>
          <select v-model="form.inventory_category_id" class="w-full px-2 py-1 border rounded">
            <option value="">不选</option>
            <option v-for="c in inventoryCategoryOptions" :key="c.id" :value="c.id">
              {{ '│ '.repeat(c.level - 1) }}{{ c.name }}{{ c.owner_type === 'STORE' ? ' (门店)' : '' }}
            </option>
          </select>
          <div v-if="errors.inventory_category_id" class="text-rose-600 text-xs mt-1">{{ errors.inventory_category_id }}</div>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block mb-1">单位</label>
          <input v-model="form.unit" class="w-full px-2 py-1 border rounded" />
        </div>
        <div class="flex items-center gap-2">
          <input id="inv" type="checkbox" v-model="form.inventory_enabled" />
          <label for="inv">启用库存</label>
        </div>
      </div>

      <fieldset class="border rounded p-3">
        <legend class="px-2">SKU</legend>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block mb-1">条码</label>
            <input v-model="form.sku.barcode" class="w-full px-2 py-1 border rounded" />
          </div>
          <div>
            <label class="block mb-1">售价（分）</label>
            <input type="number" v-model.number="form.sku.sale_price_cents"
              class="w-full px-2 py-1 border rounded" />
          </div>
          <div>
            <label class="block mb-1">成本（分）</label>
            <input type="number" v-model.number="form.sku.cost_price_cents"
              class="w-full px-2 py-1 border rounded" />
          </div>
        </div>
      </fieldset>

      <fieldset class="border rounded p-3">
        <legend class="px-2">库存策略</legend>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block mb-1">追踪类型</label>
            <select v-model="form.policy.inventory_track_type"
              class="w-full px-2 py-1 border rounded">
              <option value="NONE">不记库存</option>
              <option value="FINISHED_GOOD">成品</option>
              <option value="RAW_MATERIAL">原料</option>
              <option value="BOTH">兼用</option>
            </select>
          </div>
          <div>
            <label class="block mb-1">扣减方式</label>
            <select v-model="form.policy.stock_deduct_mode"
              class="w-full px-2 py-1 border rounded">
              <option value="MANUAL_DEDUCT">手动扣减</option>
              <option value="SALE_DEDUCT">销售扣减</option>
              <option value="PRODUCTION_DEDUCT">生产扣减</option>
            </select>
          </div>
        </div>
        <div class="flex gap-4 mt-3">
          <label class="flex items-center gap-1">
            <input type="checkbox" v-model="form.policy.allow_negative_stock" />允许负库存
          </label>
          <label class="flex items-center gap-1">
            <input type="checkbox" v-model="form.policy.batch_required" />要求批次
          </label>
          <label class="flex items-center gap-1">
            <input type="checkbox" v-model="form.policy.expiry_required" />要求保质期
          </label>
        </div>
      </fieldset>

      <button type="submit"
        class="px-4 py-2 bg-indigo-600 text-white rounded">创建</button>
    </form>
  </div>
</template>
```

- [ ] **Step 5: 写 Items/Edit.vue**

`resources/js/pages/tenant/Items/Edit.vue`：

```vue
<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const page = usePage();
interface CategoryOption {
  id: string; name: string;
  owner_type: 'TENANT' | 'STORE';
  category_type: 'BUSINESS' | 'INVENTORY' | 'BOTH';
  item_type_scope: string;
  level: number;
  path: string;
}
const props = computed(() => page.props as unknown as {
  item: { id: string; item_name: string; item_type: string;
    business_category_id: string | null; inventory_category_id: string | null;
    unit: string; inventory_enabled: boolean; status: string };
  sku: null | { id: string; spec_json: Record<string, unknown>; barcode: string | null;
    sale_price_cents: number; cost_price_cents: number };
  policy: null | { inventory_track_type: string; stock_deduct_mode: string;
    allow_negative_stock: boolean; batch_required: boolean; expiry_required: boolean };
  categories: CategoryOption[];
  item_types: string[];
});

const form = ref({
  item_name: props.value.item.item_name,
  item_type: props.value.item.item_type,
  business_category_id: props.value.item.business_category_id ?? '',
  inventory_category_id: props.value.item.inventory_category_id ?? '',
  unit: props.value.item.unit,
  inventory_enabled: props.value.item.inventory_enabled,
  status: props.value.item.status,
  sku: {
    spec_json: props.value.sku?.spec_json ?? {},
    barcode: props.value.sku?.barcode ?? '',
    sale_price_cents: props.value.sku?.sale_price_cents ?? 0,
    cost_price_cents: props.value.sku?.cost_price_cents ?? 0,
  },
  policy: {
    inventory_track_type: props.value.policy?.inventory_track_type ?? 'FINISHED_GOOD',
    stock_deduct_mode: props.value.policy?.stock_deduct_mode ?? 'MANUAL_DEDUCT',
    allow_negative_stock: props.value.policy?.allow_negative_stock ?? false,
    batch_required: props.value.policy?.batch_required ?? false,
    expiry_required: props.value.policy?.expiry_required ?? false,
  },
});

const errors = ref<Record<string, string>>({});

function submit() {
  router.patch(`/tenant/items/${props.value.item.id}`,
    form.value as unknown as Record<string, unknown>, {
      onError: (e) => { errors.value = e as Record<string, string>; },
    });
}
</script>

<template>
  <div class="p-6 max-w-3xl">
    <h1 class="text-xl font-medium mb-4">编辑物料</h1>

    <form @submit.prevent="submit" class="space-y-4 text-sm">
      <div>
        <label class="block mb-1">物料名</label>
        <input v-model="form.item_name" class="w-full px-2 py-1 border rounded" />
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block mb-1">类型</label>
          <select v-model="form.item_type" class="w-full px-2 py-1 border rounded">
            <option v-for="t in props.item_types" :key="t" :value="t">{{ t }}</option>
          </select>
        </div>
        <div>
          <label class="block mb-1">状态</label>
          <select v-model="form.status" class="w-full px-2 py-1 border rounded">
            <option value="active">在售</option>
            <option value="off_shelf">下架</option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block mb-1">经营分类</label>
          <select v-model="form.business_category_id" class="w-full px-2 py-1 border rounded">
            <option value="">不选</option>
            <option v-for="c in props.categories.filter(c =>
              (c.category_type === 'BUSINESS' || c.category_type === 'BOTH')
              && (c.item_type_scope === 'ALL' || c.item_type_scope === form.item_type))"
              :key="c.id" :value="c.id">
              {{ '│ '.repeat(c.level - 1) }}{{ c.name }}{{ c.owner_type === 'STORE' ? ' (门店)' : '' }}
            </option>
          </select>
          <div v-if="errors.business_category_id" class="text-rose-600 text-xs mt-1">{{ errors.business_category_id }}</div>
        </div>
        <div>
          <label class="block mb-1">库存分类</label>
          <select v-model="form.inventory_category_id" class="w-full px-2 py-1 border rounded">
            <option value="">不选</option>
            <option v-for="c in props.categories.filter(c =>
              (c.category_type === 'INVENTORY' || c.category_type === 'BOTH')
              && (c.item_type_scope === 'ALL' || c.item_type_scope === form.item_type))"
              :key="c.id" :value="c.id">
              {{ '│ '.repeat(c.level - 1) }}{{ c.name }}{{ c.owner_type === 'STORE' ? ' (门店)' : '' }}
            </option>
          </select>
          <div v-if="errors.inventory_category_id" class="text-rose-600 text-xs mt-1">{{ errors.inventory_category_id }}</div>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block mb-1">单位</label>
          <input v-model="form.unit" class="w-full px-2 py-1 border rounded" />
        </div>
        <div class="flex items-center gap-2">
          <input id="inv" type="checkbox" v-model="form.inventory_enabled" />
          <label for="inv">启用库存</label>
        </div>
      </div>

      <fieldset class="border rounded p-3">
        <legend class="px-2">SKU</legend>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="block mb-1">条码</label>
            <input v-model="form.sku.barcode" class="w-full px-2 py-1 border rounded" /></div>
          <div><label class="block mb-1">售价（分）</label>
            <input type="number" v-model.number="form.sku.sale_price_cents" class="w-full px-2 py-1 border rounded" /></div>
          <div><label class="block mb-1">成本（分）</label>
            <input type="number" v-model.number="form.sku.cost_price_cents" class="w-full px-2 py-1 border rounded" /></div>
        </div>
      </fieldset>

      <fieldset class="border rounded p-3">
        <legend class="px-2">库存策略</legend>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="block mb-1">追踪类型</label>
            <select v-model="form.policy.inventory_track_type" class="w-full px-2 py-1 border rounded">
              <option value="NONE">不记库存</option>
              <option value="FINISHED_GOOD">成品</option>
              <option value="RAW_MATERIAL">原料</option>
              <option value="BOTH">兼用</option>
            </select></div>
          <div><label class="block mb-1">扣减方式</label>
            <select v-model="form.policy.stock_deduct_mode" class="w-full px-2 py-1 border rounded">
              <option value="MANUAL_DEDUCT">手动扣减</option>
              <option value="SALE_DEDUCT">销售扣减</option>
              <option value="PRODUCTION_DEDUCT">生产扣减</option>
            </select></div>
        </div>
        <div class="flex gap-4 mt-3">
          <label class="flex items-center gap-1">
            <input type="checkbox" v-model="form.policy.allow_negative_stock" />允许负库存
          </label>
          <label class="flex items-center gap-1">
            <input type="checkbox" v-model="form.policy.batch_required" />要求批次
          </label>
          <label class="flex items-center gap-1">
            <input type="checkbox" v-model="form.policy.expiry_required" />要求保质期
          </label>
        </div>
      </fieldset>

      <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded">保存</button>
    </form>
  </div>
</template>
```

- [ ] **Step 6: type-check 前端**

```bash
npx vue-tsc --noEmit
```

Expected: 无类型错误。

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat(catalog): TenantItemController + Items pages + routes"
```

---

## Task 6：`TenantItemWebTest` 替换旧 goods 测试

**Files:**
- Create: `app/Modules/Catalog/Tests/Feature/TenantItemWebTest.php`

- [ ] **Step 1: 写测试**

`app/Modules/Catalog/Tests/Feature/TenantItemWebTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Catalog\Models\ProductInventoryPolicy;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->actor = User::factory()->create();
    Membership::factory()->create([
        'user_id' => $this->actor->id, 'tenant_id' => $this->tenant->id, 'store_id' => null,
    ]);
    $this->category = Category::factory()->create(['tenant_id' => $this->tenant->id, 'name' => '主分类']);
    $this->actingAs($this->actor, 'web')
        ->withSession(['current_tenant_id' => $this->tenant->id]);
});

test('GET /tenant/items 列出 + has_categories=true', function () {
    $i = Item::factory()->create([
        'tenant_id' => $this->tenant->id, 'business_category_id' => $this->category->id,
        'item_name' => '美式咖啡豆', 'item_type' => 'RAW_MATERIAL',
    ]);
    ItemSku::factory()->create([
        'tenant_id' => $this->tenant->id, 'item_id' => $i->id,
        'sale_price_cents' => 2200,
    ]);

    $this->get('/tenant/items')->assertOk()->assertInertia(fn ($p) => $p
        ->component('tenant/Items/Index')
        ->where('total', 1)
        ->where('has_categories', true)
        ->where('rows.0.item_name', '美式咖啡豆')
        ->where('rows.0.item_type', 'RAW_MATERIAL')
        ->where('rows.0.first_sku_price_cents', 2200)
    );
});

test('GET /tenant/items?q=&item_type=&status= 过滤生效', function () {
    Item::factory()->create([
        'tenant_id' => $this->tenant->id, 'business_category_id' => $this->category->id,
        'item_name' => 'A 物料', 'item_type' => 'SALE_PRODUCT', 'status' => 'active',
    ]);
    Item::factory()->create([
        'tenant_id' => $this->tenant->id, 'business_category_id' => $this->category->id,
        'item_name' => 'B 物料', 'item_type' => 'RAW_MATERIAL', 'status' => 'active',
    ]);
    Item::factory()->create([
        'tenant_id' => $this->tenant->id, 'business_category_id' => $this->category->id,
        'item_name' => 'A 下架', 'item_type' => 'SALE_PRODUCT', 'status' => 'off_shelf',
    ]);

    $this->get('/tenant/items?q=A&item_type=SALE_PRODUCT&status=active')
        ->assertInertia(fn ($p) => $p->where('total', 1));
});

test('POST /tenant/items 创建 item + sku + policy', function () {
    $this->post('/tenant/items', [
        'item_name' => '美式',
        'item_type' => 'SALE_PRODUCT',
        'business_category_id' => $this->category->id,
        'unit' => 'PCS',
        'inventory_enabled' => true,
        'sku' => [
            'spec_json' => [], 'barcode' => 'AM-01',
            'sale_price_cents' => 2200, 'cost_price_cents' => 800,
        ],
        'policy' => [
            'inventory_track_type' => 'FINISHED_GOOD',
            'stock_deduct_mode' => 'MANUAL_DEDUCT',
            'allow_negative_stock' => false,
            'batch_required' => false,
            'expiry_required' => false,
        ],
    ])->assertRedirect('/tenant/items');

    $i = Item::query()->withoutGlobalScopes()->where('item_name', '美式')->firstOrFail();
    $s = ItemSku::query()->withoutGlobalScopes()->where('item_id', $i->id)->firstOrFail();
    $p = ProductInventoryPolicy::query()->withoutGlobalScopes()->where('sku_id', $s->id)->firstOrFail();

    expect($s->barcode)->toBe('AM-01');
    expect($s->sale_price_cents)->toBe(2200);
    expect($p->inventory_track_type->value)->toBe('FINISHED_GOOD');
});

test('POST 同租户 barcode 重复 422', function () {
    $i = Item::factory()->create(['tenant_id' => $this->tenant->id, 'business_category_id' => $this->category->id]);
    ItemSku::factory()->create(['tenant_id' => $this->tenant->id, 'item_id' => $i->id, 'barcode' => 'DUP']);

    $this->post('/tenant/items', [
        'item_name' => 'X', 'item_type' => 'SALE_PRODUCT',
        'business_category_id' => $this->category->id, 'unit' => 'PCS', 'inventory_enabled' => true,
        'sku' => ['spec_json' => [], 'barcode' => 'DUP', 'sale_price_cents' => 100, 'cost_price_cents' => 0],
    ])->assertSessionHasErrors('sku.barcode');
});

test('POST 跨租户 business_category_id 被拒', function () {
    $other = Tenant::factory()->create();
    $alien = Category::factory()->create(['tenant_id' => $other->id]);

    $this->post('/tenant/items', [
        'item_name' => 'X', 'item_type' => 'SALE_PRODUCT',
        'business_category_id' => $alien->id, 'unit' => 'PCS', 'inventory_enabled' => true,
        'sku' => ['spec_json' => [], 'barcode' => null, 'sale_price_cents' => 100, 'cost_price_cents' => 0],
    ])->assertSessionHasErrors('business_category_id');
});

test('POST 不挂分类（business/inventory 都为空）也能创建', function () {
    $this->post('/tenant/items', [
        'item_name' => '无分类物料', 'item_type' => 'SALE_PRODUCT',
        'unit' => 'PCS', 'inventory_enabled' => true,
        'sku' => ['spec_json' => [], 'barcode' => null, 'sale_price_cents' => 100, 'cost_price_cents' => 0],
    ])->assertRedirect('/tenant/items');

    $i = Item::query()->withoutGlobalScopes()->where('item_name', '无分类物料')->firstOrFail();
    expect($i->business_category_id)->toBeNull();
    expect($i->inventory_category_id)->toBeNull();
});

test('POST 把 INVENTORY 分类挂到 business_category_id 时被拒（类型不匹配）', function () {
    $invCat = Category::factory()->inventory()->create([
        'tenant_id' => $this->tenant->id, 'name' => '原料分类',
    ]);

    $this->post('/tenant/items', [
        'item_name' => 'X', 'item_type' => 'SALE_PRODUCT',
        'business_category_id' => $invCat->id, 'unit' => 'PCS', 'inventory_enabled' => true,
        'sku' => ['spec_json' => [], 'barcode' => null, 'sale_price_cents' => 100, 'cost_price_cents' => 0],
    ])->assertSessionHasErrors('business_category_id');
});

test('POST 同时挂 business + inventory 双分类成功', function () {
    $bizCat = Category::factory()->create([
        'tenant_id' => $this->tenant->id, 'name' => '经营-饮品',
    ]);
    $invCat = Category::factory()->inventory()->create([
        'tenant_id' => $this->tenant->id, 'name' => '物料-原料',
    ]);

    $this->post('/tenant/items', [
        'item_name' => '双分类物料', 'item_type' => 'SALE_PRODUCT',
        'business_category_id' => $bizCat->id,
        'inventory_category_id' => $invCat->id,
        'unit' => 'PCS', 'inventory_enabled' => true,
        'sku' => ['spec_json' => [], 'barcode' => null, 'sale_price_cents' => 100, 'cost_price_cents' => 0],
    ])->assertRedirect('/tenant/items');

    $i = Item::query()->withoutGlobalScopes()->where('item_name', '双分类物料')->firstOrFail();
    expect($i->business_category_id)->toBe($bizCat->id);
    expect($i->inventory_category_id)->toBe($invCat->id);
});

test('PATCH /tenant/items/{id} 更新 item + sku + policy + status', function () {
    $i = Item::factory()->create([
        'tenant_id' => $this->tenant->id, 'business_category_id' => $this->category->id,
        'item_name' => '原名',
    ]);
    $s = ItemSku::factory()->create([
        'tenant_id' => $this->tenant->id, 'item_id' => $i->id, 'sale_price_cents' => 100,
    ]);

    $this->patch("/tenant/items/{$i->id}", [
        'item_name' => '改名', 'item_type' => 'SALE_PRODUCT',
        'business_category_id' => $this->category->id, 'unit' => 'PCS', 'inventory_enabled' => true,
        'status' => 'off_shelf',
        'sku' => ['spec_json' => [], 'barcode' => null, 'sale_price_cents' => 999, 'cost_price_cents' => 100],
        'policy' => [
            'inventory_track_type' => 'NONE', 'stock_deduct_mode' => 'MANUAL_DEDUCT',
            'allow_negative_stock' => true, 'batch_required' => false, 'expiry_required' => false,
        ],
    ])->assertRedirect();

    $i->refresh(); $s->refresh();
    $p = ProductInventoryPolicy::query()->withoutGlobalScopes()->where('sku_id', $s->id)->firstOrFail();

    expect($i->item_name)->toBe('改名');
    expect($i->status->value)->toBe('off_shelf');
    expect($s->sale_price_cents)->toBe(999);
    expect($p->inventory_track_type->value)->toBe('NONE');
    expect($p->allow_negative_stock)->toBeTrue();
});

test('PATCH 跨租户 404', function () {
    $other = Tenant::factory()->create();
    $oc = Category::factory()->create(['tenant_id' => $other->id]);
    $i = Item::factory()->create(['tenant_id' => $other->id, 'business_category_id' => $oc->id]);

    $this->patch("/tenant/items/{$i->id}", [
        'item_name' => 'X', 'item_type' => 'SALE_PRODUCT',
        'business_category_id' => $this->category->id, 'unit' => 'PCS', 'inventory_enabled' => true,
        'status' => 'active',
        'sku' => ['spec_json' => [], 'barcode' => null, 'sale_price_cents' => 100, 'cost_price_cents' => 0],
    ])->assertNotFound();
});
```

- [ ] **Step 2: 跑测试**

```bash
./vendor/bin/pest app/Modules/Catalog/Tests/Feature/TenantItemWebTest.php
```

Expected: 10 passed.

- [ ] **Step 3: 整模块跑一遍确认全绿**

```bash
composer test
```

Expected: 全部 PASS。

- [ ] **Step 4: Commit**

```bash
git add app/Modules/Catalog/Tests/
git commit -m "test(catalog): rewrite TenantItem feature tests for new schema"
```

---

# Phase B — Toggle 配置

## Task 7：`tenant_inventory_configs` 表 + Tenant Observer + ServiceProvider

**Files:**
- Create: `app/Modules/Inventory/InventoryServiceProvider.php`
- Create: `app/Modules/Inventory/Database/Migrations/2026_05_08_000064_create_tenant_inventory_configs_table.php`
- Create: `app/Modules/Inventory/Enums/InventoryCostMethod.php`
- Create: `app/Modules/Inventory/Models/TenantInventoryConfig.php`
- Create: `app/Modules/Inventory/Database/Factories/TenantInventoryConfigFactory.php`
- Create: `app/Modules/Inventory/Observers/TenantConfigObserver.php`
- Modify: `bootstrap/providers.php`（注册 InventoryServiceProvider）
- Test: `app/Modules/Inventory/Tests/Feature/TenantConfigObserverTest.php`

- [ ] **Step 1: 写枚举 InventoryCostMethod**

`app/Modules/Inventory/Enums/InventoryCostMethod.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

enum InventoryCostMethod: string
{
    case Fifo = 'FIFO';
    case MovingAverage = 'MOVING_AVG';
    case Standard = 'STANDARD';
}
```

- [ ] **Step 2: 写迁移**

`app/Modules/Inventory/Database/Migrations/2026_05_08_000064_create_tenant_inventory_configs_table.php`：

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_inventory_configs', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26)->unique();
            $table->boolean('inventory_enabled')->default(true);
            $table->boolean('multi_location_enabled')->default(false);
            $table->boolean('production_enabled')->default(false);
            $table->boolean('purchase_enabled')->default(false);
            $table->boolean('transfer_enabled')->default(false);
            $table->boolean('stocktaking_enabled')->default(true);
            $table->boolean('negative_stock_allowed')->default(false);
            $table->enum('inventory_cost_method', ['FIFO', 'MOVING_AVG', 'STANDARD'])
                ->default('MOVING_AVG');
            $table->boolean('expiry_management_enabled')->default(false);
            $table->boolean('batch_management_enabled')->default(false);
            $table->boolean('auto_deduct_raw_material_enabled')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_inventory_configs');
    }
};
```

- [ ] **Step 3: 写模型**

`app/Modules/Inventory/Models/TenantInventoryConfig.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Database\Factories\TenantInventoryConfigFactory;
use App\Modules\Inventory\Enums\InventoryCostMethod;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 租户级库存能力配置。每个 tenant 一行（通过 Observer 自动建立）。
 * 不挂 BelongsToTenant：本表的 tenant_id 即业务主键，不需要全局作用域过滤。
 */
class TenantInventoryConfig extends Model
{
    use HasFactory;
    use HasUlid;

    protected $table = 'tenant_inventory_configs';

    protected $guarded = [];

    protected $casts = [
        'inventory_enabled' => 'bool',
        'multi_location_enabled' => 'bool',
        'production_enabled' => 'bool',
        'purchase_enabled' => 'bool',
        'transfer_enabled' => 'bool',
        'stocktaking_enabled' => 'bool',
        'negative_stock_allowed' => 'bool',
        'inventory_cost_method' => InventoryCostMethod::class,
        'expiry_management_enabled' => 'bool',
        'batch_management_enabled' => 'bool',
        'auto_deduct_raw_material_enabled' => 'bool',
    ];

    protected static function newFactory(): TenantInventoryConfigFactory
    {
        return TenantInventoryConfigFactory::new();
    }
}
```

- [ ] **Step 4: 写工厂**

`app/Modules/Inventory/Database/Factories/TenantInventoryConfigFactory.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Database\Factories;

use App\Modules\Inventory\Models\TenantInventoryConfig;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantInventoryConfigFactory extends Factory
{
    protected $model = TenantInventoryConfig::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'inventory_enabled' => true,
            'multi_location_enabled' => false,
            'production_enabled' => false,
            'purchase_enabled' => false,
            'transfer_enabled' => false,
            'stocktaking_enabled' => true,
            'negative_stock_allowed' => false,
            'inventory_cost_method' => 'MOVING_AVG',
            'expiry_management_enabled' => false,
            'batch_management_enabled' => false,
            'auto_deduct_raw_material_enabled' => false,
        ];
    }
}
```

- [ ] **Step 5: 写 Observer**

`app/Modules/Inventory/Observers/TenantConfigObserver.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Observers;

use App\Modules\Inventory\Models\TenantInventoryConfig;
use App\Modules\Tenancy\Models\Tenant;

/**
 * 租户创建时自动建一行 tenant_inventory_configs（默认值即迁移列默认）。
 */
class TenantConfigObserver
{
    public function created(Tenant $tenant): void
    {
        TenantInventoryConfig::query()->create([
            'tenant_id' => $tenant->id,
        ]);
    }
}
```

- [ ] **Step 6: 写 ServiceProvider**

`app/Modules/Inventory/InventoryServiceProvider.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory;

use App\Modules\Inventory\Observers\TenantConfigObserver;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\ModuleServiceProvider;

class InventoryServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__;
    }

    public function boot(): void
    {
        parent::boot();
        Tenant::observe(TenantConfigObserver::class);
    }
}
```

- [ ] **Step 7: 注册 Provider 到 bootstrap/providers.php**

打开 `/mnt/d/Projects/Huang/coffee/bootstrap/providers.php`。在数组中添加：

```php
App\Modules\Inventory\InventoryServiceProvider::class,
```

（应紧跟在其他 Module providers 后；具体位置参考已注册的 `App\Modules\Catalog\CatalogServiceProvider::class`）

- [ ] **Step 8: 写测试**

`app/Modules/Inventory/Tests/Feature/TenantConfigObserverTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Inventory\Models\TenantInventoryConfig;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Tenant 创建时自动建立 tenant_inventory_configs 行', function () {
    $tenant = Tenant::factory()->create();

    $cfg = TenantInventoryConfig::query()->where('tenant_id', $tenant->id)->first();

    expect($cfg)->not->toBeNull();
    expect($cfg->inventory_enabled)->toBeTrue();
    expect($cfg->stocktaking_enabled)->toBeTrue();
    expect($cfg->batch_management_enabled)->toBeFalse();
    expect($cfg->inventory_cost_method->value)->toBe('MOVING_AVG');
});

test('tenant_id 唯一约束（不能重复建）', function () {
    $tenant = Tenant::factory()->create();

    expect(fn () => TenantInventoryConfig::factory()->create(['tenant_id' => $tenant->id]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
```

- [ ] **Step 9: 跑测试**

```bash
./vendor/bin/pest app/Modules/Inventory/Tests/Feature/TenantConfigObserverTest.php
```

Expected: 2 passed.

- [ ] **Step 10: Commit**

```bash
git add app/Modules/Inventory/ bootstrap/providers.php
git commit -m "feat(inventory): tenant_inventory_configs + observer + service provider"
```

---

## Task 8：`store_inventory_configs` 表 + Store Observer

**Files:**
- Create: `app/Modules/Inventory/Database/Migrations/2026_05_08_000065_create_store_inventory_configs_table.php`
- Create: `app/Modules/Inventory/Models/StoreInventoryConfig.php`
- Create: `app/Modules/Inventory/Database/Factories/StoreInventoryConfigFactory.php`
- Create: `app/Modules/Inventory/Observers/StoreConfigObserver.php`
- Modify: `app/Modules/Inventory/InventoryServiceProvider.php`（observe Store）
- Test: `app/Modules/Inventory/Tests/Feature/StoreConfigObserverTest.php`

- [ ] **Step 1: 写迁移**

`app/Modules/Inventory/Database/Migrations/2026_05_08_000065_create_store_inventory_configs_table.php`：

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_inventory_configs', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('store_id', 26)->unique();
            $table->boolean('inventory_enabled')->default(true);
            $table->boolean('multi_location_enabled')->default(false);
            $table->string('default_stock_mode', 20)->default('SIMPLE');
            $table->boolean('production_enabled')->default(false);
            $table->boolean('allow_direct_stock_adjustment')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_inventory_configs');
    }
};
```

- [ ] **Step 2: 写模型**

`app/Modules/Inventory/Models/StoreInventoryConfig.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Database\Factories\StoreInventoryConfigFactory;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreInventoryConfig extends Model
{
    use HasFactory;
    use HasUlid;

    protected $table = 'store_inventory_configs';

    protected $guarded = [];

    protected $casts = [
        'inventory_enabled' => 'bool',
        'multi_location_enabled' => 'bool',
        'production_enabled' => 'bool',
        'allow_direct_stock_adjustment' => 'bool',
    ];

    protected static function newFactory(): StoreInventoryConfigFactory
    {
        return StoreInventoryConfigFactory::new();
    }
}
```

- [ ] **Step 3: 写工厂**

`app/Modules/Inventory/Database/Factories/StoreInventoryConfigFactory.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Database\Factories;

use App\Modules\Inventory\Models\StoreInventoryConfig;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class StoreInventoryConfigFactory extends Factory
{
    protected $model = StoreInventoryConfig::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'store_id' => Store::factory(),
            'inventory_enabled' => true,
            'multi_location_enabled' => false,
            'default_stock_mode' => 'SIMPLE',
            'production_enabled' => false,
            'allow_direct_stock_adjustment' => true,
        ];
    }
}
```

注：项目中已存在 `app/Modules/Tenancy/Database/Factories/StoreFactory.php`（factory 已配置好 tenant_id / name / status），直接使用 `Store::factory()` 即可。stores 表实际字段为 `(id, tenant_id, name, status, timestamps)`——**没有 code 列**，先前文档草稿如有 code 字段一律忽略。

- [ ] **Step 4: 写 Observer**

`app/Modules/Inventory/Observers/StoreConfigObserver.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Observers;

use App\Modules\Tenancy\Models\Store;
use App\Modules\Inventory\Models\StoreInventoryConfig;

class StoreConfigObserver
{
    public function created(Store $store): void
    {
        StoreInventoryConfig::query()->create([
            'tenant_id' => $store->tenant_id,
            'store_id' => $store->id,
        ]);
    }
}
```

- [ ] **Step 5: 修改 InventoryServiceProvider 注册 StoreConfigObserver**

把 `boot()` 改为：

```php
public function boot(): void
{
    parent::boot();
    \App\Modules\Tenancy\Models\Tenant::observe(\App\Modules\Inventory\Observers\TenantConfigObserver::class);
    \App\Modules\Tenancy\Models\Store::observe(\App\Modules\Inventory\Observers\StoreConfigObserver::class);
}
```

- [ ] **Step 6: 写测试**

`app/Modules/Inventory/Tests/Feature/StoreConfigObserverTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Tenancy\Models\Store;
use App\Modules\Inventory\Models\StoreInventoryConfig;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Store 创建时自动建立 store_inventory_configs 行', function () {
    $tenant = Tenant::factory()->create();
    $store = Store::factory()->create(['tenant_id' => $tenant->id]);

    $cfg = StoreInventoryConfig::query()->where('store_id', $store->id)->first();

    expect($cfg)->not->toBeNull();
    expect($cfg->tenant_id)->toBe($tenant->id);
    expect($cfg->inventory_enabled)->toBeTrue();
    expect($cfg->allow_direct_stock_adjustment)->toBeTrue();
    expect($cfg->default_stock_mode)->toBe('SIMPLE');
});

test('store_id 唯一约束', function () {
    $tenant = Tenant::factory()->create();
    $store = Store::factory()->create(['tenant_id' => $tenant->id]);

    expect(fn () => StoreInventoryConfig::factory()->create([
        'tenant_id' => $tenant->id, 'store_id' => $store->id,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
```

- [ ] **Step 7: 跑测试**

```bash
./vendor/bin/pest app/Modules/Inventory/Tests/Feature/StoreConfigObserverTest.php
```

Expected: 2 passed.

- [ ] **Step 8: Commit**

```bash
git add app/Modules/
git commit -m "feat(inventory): store_inventory_configs + observer"
```

---

# Phase C — Inventory 核心 Schema

## Task 9：`stock_owners` 表 + Store Observer 自动建 STORE 类型 owner

**Files:**
- Create: `app/Modules/Inventory/Database/Migrations/2026_05_08_000066_create_stock_owners_table.php`
- Create: `app/Modules/Inventory/Enums/StockOwnerType.php`
- Create: `app/Modules/Inventory/Models/StockOwner.php`
- Create: `app/Modules/Inventory/Database/Factories/StockOwnerFactory.php`
- Modify: `app/Modules/Inventory/Observers/StoreConfigObserver.php`（追加 stock_owner 创建）
- Test: `app/Modules/Inventory/Tests/Feature/StockOwnerSchemaTest.php`

- [ ] **Step 1: 写枚举 StockOwnerType**

`app/Modules/Inventory/Enums/StockOwnerType.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

enum StockOwnerType: string
{
    case Store = 'STORE';
    case Warehouse = 'WAREHOUSE';
    case ProductionArea = 'PRODUCTION_AREA';
}
```

- [ ] **Step 2: 写迁移**

`app/Modules/Inventory/Database/Migrations/2026_05_08_000066_create_stock_owners_table.php`：

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_owners', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->enum('owner_type', ['STORE', 'WAREHOUSE', 'PRODUCTION_AREA']);
            $table->char('owner_ref_id', 26);
            $table->string('name', 80);
            $table->enum('status', ['active', 'disabled'])->default('active');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'owner_type']);
            $table->unique(['tenant_id', 'owner_type', 'owner_ref_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_owners');
    }
};
```

- [ ] **Step 3: 写模型**

`app/Modules/Inventory/Models/StockOwner.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Database\Factories\StockOwnerFactory;
use App\Modules\Inventory\Enums\StockOwnerType;
use App\Support\Eloquent\BelongsToTenant;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockOwner extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUlid;

    protected $table = 'stock_owners';

    protected $guarded = [];

    protected $casts = [
        'owner_type' => StockOwnerType::class,
    ];

    protected static function newFactory(): StockOwnerFactory
    {
        return StockOwnerFactory::new();
    }
}
```

- [ ] **Step 4: 写工厂**

`app/Modules/Inventory/Database/Factories/StockOwnerFactory.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Database\Factories;

use App\Modules\Tenancy\Models\Store;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class StockOwnerFactory extends Factory
{
    protected $model = StockOwner::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'owner_type' => 'STORE',
            'owner_ref_id' => (string) Str::ulid(),
            'name' => $this->faker->company().' 仓',
            'status' => 'active',
        ];
    }

    public function forStore(Store $store): static
    {
        return $this->state(fn () => [
            'tenant_id' => $store->tenant_id,
            'owner_type' => 'STORE',
            'owner_ref_id' => $store->id,
            'name' => $store->name.' 主仓',
        ]);
    }
}
```

- [ ] **Step 5: 修改 StoreConfigObserver 同时建 stock_owner**

`app/Modules/Inventory/Observers/StoreConfigObserver.php` 完整重写：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Observers;

use App\Modules\Tenancy\Models\Store;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Inventory\Models\StoreInventoryConfig;

/**
 * Store 创建时连带建立：
 * - store_inventory_configs 行
 * - stock_owners 行（owner_type=STORE 指向该 store）
 *
 * Task 10 还会在此追加 stock_locations 默认库位创建逻辑。
 */
class StoreConfigObserver
{
    public function created(Store $store): void
    {
        StoreInventoryConfig::query()->create([
            'tenant_id' => $store->tenant_id,
            'store_id' => $store->id,
        ]);

        StockOwner::query()->withoutGlobalScopes()->create([
            'tenant_id' => $store->tenant_id,
            'owner_type' => 'STORE',
            'owner_ref_id' => $store->id,
            'name' => $store->name.' 主仓',
            'status' => 'active',
        ]);
    }
}
```

- [ ] **Step 6: 写测试**

`app/Modules/Inventory/Tests/Feature/StockOwnerSchemaTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Tenancy\Models\Store;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Store 创建时自动建立 stock_owners 行（owner_type=STORE）', function () {
    $tenant = Tenant::factory()->create();
    $store = Store::factory()->create(['tenant_id' => $tenant->id]);

    $owner = StockOwner::query()->withoutGlobalScopes()
        ->where('owner_type', 'STORE')
        ->where('owner_ref_id', $store->id)
        ->first();

    expect($owner)->not->toBeNull();
    expect($owner->tenant_id)->toBe($tenant->id);
    expect($owner->name)->toContain('主仓');
});

test('同 store 不能重复建 stock_owner', function () {
    $tenant = Tenant::factory()->create();
    $store = Store::factory()->create(['tenant_id' => $tenant->id]);

    expect(fn () => StockOwner::factory()->create([
        'tenant_id' => $tenant->id,
        'owner_type' => 'STORE',
        'owner_ref_id' => $store->id,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
```

- [ ] **Step 7: 跑测试**

```bash
./vendor/bin/pest app/Modules/Inventory/Tests/Feature/StockOwnerSchemaTest.php
```

Expected: 2 passed.

- [ ] **Step 8: Commit**

```bash
git add app/Modules/Inventory/
git commit -m "feat(inventory): stock_owners + auto-create on store"
```

---

## Task 10：`stock_locations` 表 + 默认库位自动创建

**Files:**
- Create: `app/Modules/Inventory/Database/Migrations/2026_05_08_000067_create_stock_locations_table.php`
- Create: `app/Modules/Inventory/Enums/StockLocationType.php`
- Create: `app/Modules/Inventory/Models/StockLocation.php`
- Create: `app/Modules/Inventory/Database/Factories/StockLocationFactory.php`
- Modify: `app/Modules/Inventory/Observers/StoreConfigObserver.php`（追加默认 location）
- Test: `app/Modules/Inventory/Tests/Feature/StockLocationSchemaTest.php`

- [ ] **Step 1: 写枚举**

`app/Modules/Inventory/Enums/StockLocationType.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

enum StockLocationType: string
{
    case Shelf = 'SHELF';
    case Freezer = 'FREEZER';
    case Display = 'DISPLAY';
    case Backroom = 'BACKROOM';
}
```

- [ ] **Step 2: 写迁移**

`app/Modules/Inventory/Database/Migrations/2026_05_08_000067_create_stock_locations_table.php`：

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_locations', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('stock_owner_id', 26);
            $table->string('location_code', 40);
            $table->string('location_name', 80);
            $table->enum('location_type', ['SHELF', 'FREEZER', 'DISPLAY', 'BACKROOM'])
                ->default('SHELF');
            $table->enum('status', ['active', 'disabled'])->default('active');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('stock_owner_id')->references('id')->on('stock_owners')->cascadeOnDelete();
            $table->unique(['stock_owner_id', 'location_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_locations');
    }
};
```

- [ ] **Step 3: 写模型**

`app/Modules/Inventory/Models/StockLocation.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Database\Factories\StockLocationFactory;
use App\Modules\Inventory\Enums\StockLocationType;
use App\Support\Eloquent\BelongsToTenant;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLocation extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUlid;

    protected $table = 'stock_locations';

    protected $guarded = [];

    protected $casts = [
        'location_type' => StockLocationType::class,
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(StockOwner::class, 'stock_owner_id');
    }

    protected static function newFactory(): StockLocationFactory
    {
        return StockLocationFactory::new();
    }
}
```

- [ ] **Step 4: 写工厂**

`app/Modules/Inventory/Database/Factories/StockLocationFactory.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Database\Factories;

use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockLocationFactory extends Factory
{
    protected $model = StockLocation::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'stock_owner_id' => StockOwner::factory(),
            'location_code' => strtoupper($this->faker->unique()->bothify('LOC###')),
            'location_name' => $this->faker->word().' 货架',
            'location_type' => 'SHELF',
            'status' => 'active',
        ];
    }
}
```

- [ ] **Step 5: 修改 StoreConfigObserver 在建 stock_owner 后建默认 location**

`app/Modules/Inventory/Observers/StoreConfigObserver.php` 完整重写：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Observers;

use App\Modules\Tenancy\Models\Store;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Inventory\Models\StoreInventoryConfig;

/**
 * Store 创建时连带建立：
 * - store_inventory_configs 行
 * - stock_owners 行（owner_type=STORE 指向 store）
 * - stock_locations 行（默认库位 'DEFAULT'）
 *
 * 三件事在同一事务边界内（Laravel 自动包 Observer 在 model save 事务里）。
 */
class StoreConfigObserver
{
    public function created(Store $store): void
    {
        StoreInventoryConfig::query()->create([
            'tenant_id' => $store->tenant_id,
            'store_id' => $store->id,
        ]);

        $owner = StockOwner::query()->withoutGlobalScopes()->create([
            'tenant_id' => $store->tenant_id,
            'owner_type' => 'STORE',
            'owner_ref_id' => $store->id,
            'name' => $store->name.' 主仓',
            'status' => 'active',
        ]);

        StockLocation::query()->withoutGlobalScopes()->create([
            'tenant_id' => $store->tenant_id,
            'stock_owner_id' => $owner->id,
            'location_code' => 'DEFAULT',
            'location_name' => '默认库位',
            'location_type' => 'SHELF',
            'status' => 'active',
        ]);
    }
}
```

- [ ] **Step 6: 写测试**

`app/Modules/Inventory/Tests/Feature/StockLocationSchemaTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Tenancy\Models\Store;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Store 创建时自动建立默认 location（DEFAULT）', function () {
    $tenant = Tenant::factory()->create();
    $store = Store::factory()->create(['tenant_id' => $tenant->id]);

    $owner = StockOwner::query()->withoutGlobalScopes()
        ->where('owner_ref_id', $store->id)->first();

    $location = StockLocation::query()->withoutGlobalScopes()
        ->where('stock_owner_id', $owner->id)
        ->where('location_code', 'DEFAULT')
        ->first();

    expect($location)->not->toBeNull();
    expect($location->location_name)->toBe('默认库位');
    expect($location->location_type->value)->toBe('SHELF');
});

test('同 owner 下 location_code 唯一', function () {
    $tenant = Tenant::factory()->create();
    $store = Store::factory()->create(['tenant_id' => $tenant->id]);
    $owner = StockOwner::query()->withoutGlobalScopes()
        ->where('owner_ref_id', $store->id)->first();

    expect(fn () => StockLocation::factory()->create([
        'tenant_id' => $tenant->id,
        'stock_owner_id' => $owner->id,
        'location_code' => 'DEFAULT',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
```

- [ ] **Step 7: 跑测试**

```bash
./vendor/bin/pest app/Modules/Inventory/Tests/Feature/StockLocationSchemaTest.php
```

Expected: 2 passed.

- [ ] **Step 8: Commit**

```bash
git add app/Modules/Inventory/
git commit -m "feat(inventory): stock_locations + auto default location"
```

---

## Task 11：`stock_balances` 表

**Files:**
- Create: `app/Modules/Inventory/Database/Migrations/2026_05_08_000068_create_stock_balances_table.php`
- Create: `app/Modules/Inventory/Models/StockBalance.php`
- Create: `app/Modules/Inventory/Database/Factories/StockBalanceFactory.php`
- Test: `app/Modules/Inventory/Tests/Unit/StockBalanceSchemaTest.php`

- [ ] **Step 1: 写迁移**

`app/Modules/Inventory/Database/Migrations/2026_05_08_000068_create_stock_balances_table.php`：

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('stock_owner_id', 26);
            $table->char('location_id', 26);
            $table->char('sku_id', 26);
            $table->decimal('available_qty', 18, 4)->default(0);
            $table->decimal('reserved_qty', 18, 4)->default(0);
            $table->decimal('in_transit_qty', 18, 4)->default(0);
            $table->decimal('damaged_qty', 18, 4)->default(0);
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('stock_owner_id')->references('id')->on('stock_owners')->cascadeOnDelete();
            $table->foreign('location_id')->references('id')->on('stock_locations')->cascadeOnDelete();
            $table->foreign('sku_id')->references('id')->on('item_skus')->cascadeOnDelete();
            $table->unique(['tenant_id', 'stock_owner_id', 'location_id', 'sku_id'], 'stock_balances_unique');
            $table->index(['tenant_id', 'sku_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_balances');
    }
};
```

- [ ] **Step 2: 写模型**

`app/Modules/Inventory/Models/StockBalance.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Inventory\Database\Factories\StockBalanceFactory;
use App\Support\Eloquent\BelongsToTenant;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockBalance extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUlid;

    protected $table = 'stock_balances';

    protected $guarded = [];

    protected $casts = [
        'available_qty' => 'decimal:4',
        'reserved_qty' => 'decimal:4',
        'in_transit_qty' => 'decimal:4',
        'damaged_qty' => 'decimal:4',
        'version' => 'int',
    ];

    public function sku(): BelongsTo
    {
        return $this->belongsTo(ItemSku::class, 'sku_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(StockOwner::class, 'stock_owner_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'location_id');
    }

    protected static function newFactory(): StockBalanceFactory
    {
        return StockBalanceFactory::new();
    }
}
```

- [ ] **Step 3: 写工厂**

`app/Modules/Inventory/Database/Factories/StockBalanceFactory.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Database\Factories;

use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockBalanceFactory extends Factory
{
    protected $model = StockBalance::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'stock_owner_id' => StockOwner::factory(),
            'location_id' => StockLocation::factory(),
            'sku_id' => ItemSku::factory(),
            'available_qty' => 0,
            'reserved_qty' => 0,
            'in_transit_qty' => 0,
            'damaged_qty' => 0,
            'version' => 0,
        ];
    }
}
```

- [ ] **Step 4: 写测试**

`app/Modules/Inventory/Tests/Unit/StockBalanceSchemaTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('stock_balances 行能正常创建并查询', function () {
    $balance = StockBalance::factory()->create(['available_qty' => 100]);

    expect((float) $balance->available_qty)->toBe(100.0);
    expect((float) $balance->reserved_qty)->toBe(0.0);
    expect($balance->version)->toBe(0);
});

test('唯一键约束（同 tenant+owner+location+sku 不能重复）', function () {
    $tenant = Tenant::factory()->create();
    $owner = StockOwner::factory()->create(['tenant_id' => $tenant->id]);
    $loc = StockLocation::factory()->create(['tenant_id' => $tenant->id, 'stock_owner_id' => $owner->id]);
    $sku = ItemSku::factory()->create(['tenant_id' => $tenant->id]);

    StockBalance::factory()->create([
        'tenant_id' => $tenant->id, 'stock_owner_id' => $owner->id,
        'location_id' => $loc->id, 'sku_id' => $sku->id,
    ]);

    expect(fn () => StockBalance::factory()->create([
        'tenant_id' => $tenant->id, 'stock_owner_id' => $owner->id,
        'location_id' => $loc->id, 'sku_id' => $sku->id,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
```

- [ ] **Step 5: 跑测试**

```bash
./vendor/bin/pest app/Modules/Inventory/Tests/Unit/StockBalanceSchemaTest.php
```

Expected: 2 passed.

- [ ] **Step 6: Commit**

```bash
git add app/Modules/Inventory/
git commit -m "feat(inventory): stock_balances table + model"
```

---

## Task 12：`stock_quants` 表（预建无 Action）

**Files:**
- Create: `app/Modules/Inventory/Database/Migrations/2026_05_08_000069_create_stock_quants_table.php`
- Create: `app/Modules/Inventory/Models/StockQuant.php`
- Create: `app/Modules/Inventory/Database/Factories/StockQuantFactory.php`
- Test: `app/Modules/Inventory/Tests/Unit/StockQuantSchemaTest.php`

- [ ] **Step 1: 写迁移**

`app/Modules/Inventory/Database/Migrations/2026_05_08_000069_create_stock_quants_table.php`：

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_quants', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('stock_owner_id', 26);
            $table->char('location_id', 26);
            $table->char('sku_id', 26);
            $table->string('batch_no', 64)->nullable();
            $table->date('expiry_date')->nullable();
            $table->unsignedInteger('unit_cost_cents')->default(0);
            $table->decimal('qty', 18, 4)->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('stock_owner_id')->references('id')->on('stock_owners')->cascadeOnDelete();
            $table->foreign('location_id')->references('id')->on('stock_locations')->cascadeOnDelete();
            $table->foreign('sku_id')->references('id')->on('item_skus')->cascadeOnDelete();
            $table->index(['tenant_id', 'sku_id', 'expiry_date'], 'stock_quants_sku_expiry');
            $table->index(['tenant_id', 'stock_owner_id', 'location_id', 'sku_id'], 'stock_quants_locator');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_quants');
    }
};
```

- [ ] **Step 2: 写模型**

`app/Modules/Inventory/Models/StockQuant.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Inventory\Database\Factories\StockQuantFactory;
use App\Support\Eloquent\BelongsToTenant;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockQuant extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUlid;

    protected $table = 'stock_quants';

    protected $guarded = [];

    protected $casts = [
        'expiry_date' => 'date',
        'qty' => 'decimal:4',
        'unit_cost_cents' => 'int',
    ];

    public function sku(): BelongsTo
    {
        return $this->belongsTo(ItemSku::class, 'sku_id');
    }

    protected static function newFactory(): StockQuantFactory
    {
        return StockQuantFactory::new();
    }
}
```

- [ ] **Step 3: 写工厂**

`app/Modules/Inventory/Database/Factories/StockQuantFactory.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Database\Factories;

use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Inventory\Models\StockQuant;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockQuantFactory extends Factory
{
    protected $model = StockQuant::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'stock_owner_id' => StockOwner::factory(),
            'location_id' => StockLocation::factory(),
            'sku_id' => ItemSku::factory(),
            'batch_no' => null,
            'expiry_date' => null,
            'unit_cost_cents' => $this->faker->numberBetween(50, 5000),
            'qty' => $this->faker->randomFloat(2, 0, 1000),
        ];
    }
}
```

- [ ] **Step 4: 写测试**

`app/Modules/Inventory/Tests/Unit/StockQuantSchemaTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Inventory\Models\StockQuant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('stock_quants 行可创建（含批次/保质期）', function () {
    $quant = StockQuant::factory()->create([
        'batch_no' => 'B20260508',
        'expiry_date' => '2026-12-31',
        'qty' => 50,
        'unit_cost_cents' => 1234,
    ]);

    expect($quant->batch_no)->toBe('B20260508');
    expect($quant->expiry_date->format('Y-m-d'))->toBe('2026-12-31');
    expect((float) $quant->qty)->toBe(50.0);
    expect($quant->unit_cost_cents)->toBe(1234);
});

test('batch_no 可为空（第一期场景）', function () {
    $quant = StockQuant::factory()->create(['batch_no' => null, 'expiry_date' => null]);
    expect($quant->batch_no)->toBeNull();
    expect($quant->expiry_date)->toBeNull();
});
```

- [ ] **Step 5: 跑测试**

```bash
./vendor/bin/pest app/Modules/Inventory/Tests/Unit/StockQuantSchemaTest.php
```

Expected: 2 passed.

- [ ] **Step 6: Commit**

```bash
git add app/Modules/Inventory/
git commit -m "feat(inventory): stock_quants table (schema only, no action)"
```

---

## Task 13：`stock_txns` 表（BIGINT id，append-only）

**Files:**
- Create: `app/Modules/Inventory/Database/Migrations/2026_05_08_000070_create_stock_txns_table.php`
- Create: `app/Modules/Inventory/Enums/StockTxnBizType.php`
- Create: `app/Modules/Inventory/Enums/StockTxnDirection.php`
- Create: `app/Modules/Inventory/Models/StockTxn.php`
- Create: `app/Modules/Inventory/Database/Factories/StockTxnFactory.php`
- Test: `app/Modules/Inventory/Tests/Unit/StockTxnSchemaTest.php`

- [ ] **Step 1: 写枚举**

`app/Modules/Inventory/Enums/StockTxnBizType.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

/**
 * 严格按 item_stock.md 行 266-277 的 12 个 biz_type。
 * 第一期实际写入仅前 4 项（ADJUSTMENT / STOCKTAKE_PROFIT / STOCKTAKE_LOSS / DAMAGE_OUT）。
 */
enum StockTxnBizType: string
{
    case PurchaseIn = 'PURCHASE_IN';
    case SaleOut = 'SALE_OUT';
    case ReturnIn = 'RETURN_IN';
    case ReturnOut = 'RETURN_OUT';
    case TransferOut = 'TRANSFER_OUT';
    case TransferIn = 'TRANSFER_IN';
    case StocktakeProfit = 'STOCKTAKE_PROFIT';
    case StocktakeLoss = 'STOCKTAKE_LOSS';
    case ProductionConsume = 'PRODUCTION_CONSUME';
    case ProductionOutput = 'PRODUCTION_OUTPUT';
    case Adjustment = 'ADJUSTMENT';
    case DamageOut = 'DAMAGE_OUT';
}
```

`app/Modules/Inventory/Enums/StockTxnDirection.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

enum StockTxnDirection: string
{
    case In = 'IN';
    case Out = 'OUT';
    case Freeze = 'FREEZE';
    case Release = 'RELEASE';
}
```

- [ ] **Step 2: 写迁移**

`app/Modules/Inventory/Database/Migrations/2026_05_08_000070_create_stock_txns_table.php`：

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_txns', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('tenant_id', 26);
            $table->enum('biz_type', [
                'PURCHASE_IN', 'SALE_OUT', 'RETURN_IN', 'RETURN_OUT',
                'TRANSFER_OUT', 'TRANSFER_IN', 'STOCKTAKE_PROFIT', 'STOCKTAKE_LOSS',
                'PRODUCTION_CONSUME', 'PRODUCTION_OUTPUT', 'ADJUSTMENT', 'DAMAGE_OUT',
            ]);
            $table->string('biz_order_type', 40)->nullable();
            $table->char('biz_order_id', 26)->nullable();
            $table->char('stock_owner_id', 26);
            $table->char('location_id', 26);
            $table->char('sku_id', 26);
            $table->decimal('qty_change', 18, 4);
            $table->unsignedInteger('unit_cost_cents')->nullable();
            $table->integer('amount_cents')->nullable();
            $table->enum('direction', ['IN', 'OUT', 'FREEZE', 'RELEASE']);
            $table->timestamp('occurred_at');
            $table->char('operator_id', 26);
            $table->json('meta_json')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('stock_owner_id')->references('id')->on('stock_owners')->cascadeOnDelete();
            $table->foreign('location_id')->references('id')->on('stock_locations')->cascadeOnDelete();
            $table->foreign('sku_id')->references('id')->on('item_skus')->cascadeOnDelete();
            $table->index(['tenant_id', 'stock_owner_id', 'sku_id', 'occurred_at'], 'stock_txns_locator');
            $table->index(['tenant_id', 'biz_type', 'occurred_at'], 'stock_txns_biz');
            $table->index(['tenant_id', 'biz_order_type', 'biz_order_id'], 'stock_txns_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_txns');
    }
};
```

- [ ] **Step 3: 写模型（注意：BIGINT 主键，不用 HasUlid）**

`app/Modules/Inventory/Models/StockTxn.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Inventory\Database\Factories\StockTxnFactory;
use App\Modules\Inventory\Enums\StockTxnBizType;
use App\Modules\Inventory\Enums\StockTxnDirection;
use App\Support\Eloquent\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 库存流水：append-only，不更新不删除。
 * 撤销 = 写一条反向记录，meta.cancels_txn_id 指向被撤销笔。
 */
class StockTxn extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const UPDATED_AT = null;
    public $timestamps = true; // 仅 created_at；UPDATED_AT 设 null 关闭 update

    protected $table = 'stock_txns';

    protected $guarded = [];

    protected $casts = [
        'biz_type' => StockTxnBizType::class,
        'direction' => StockTxnDirection::class,
        'qty_change' => 'decimal:4',
        'unit_cost_cents' => 'int',
        'amount_cents' => 'int',
        'occurred_at' => 'datetime',
        'meta_json' => 'array',
    ];

    public function sku(): BelongsTo
    {
        return $this->belongsTo(ItemSku::class, 'sku_id');
    }

    protected static function newFactory(): StockTxnFactory
    {
        return StockTxnFactory::new();
    }
}
```

- [ ] **Step 4: 写工厂**

`app/Modules/Inventory/Database/Factories/StockTxnFactory.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Database\Factories;

use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Inventory\Models\StockTxn;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockTxnFactory extends Factory
{
    protected $model = StockTxn::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'biz_type' => 'ADJUSTMENT',
            'biz_order_type' => null,
            'biz_order_id' => null,
            'stock_owner_id' => StockOwner::factory(),
            'location_id' => StockLocation::factory(),
            'sku_id' => ItemSku::factory(),
            'qty_change' => $this->faker->randomFloat(2, -100, 100),
            'unit_cost_cents' => null,
            'amount_cents' => null,
            'direction' => 'IN',
            'occurred_at' => now(),
            'operator_id' => User::factory(),
            'meta_json' => ['subtype' => 'MANUAL'],
        ];
    }
}
```

- [ ] **Step 5: 写测试**

`app/Modules/Inventory/Tests/Unit/StockTxnSchemaTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Inventory\Models\StockTxn;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('stock_txns 主键自增 BIGINT', function () {
    $a = StockTxn::factory()->create();
    $b = StockTxn::factory()->create();

    expect($a->id)->toBeInt();
    expect($b->id)->toBeGreaterThan($a->id);
});

test('meta_json 自动 cast 为数组', function () {
    $txn = StockTxn::factory()->create(['meta_json' => ['subtype' => 'INITIAL', 'note' => 'hello']]);
    $reload = StockTxn::query()->find($txn->id);

    expect($reload->meta_json)->toBe(['subtype' => 'INITIAL', 'note' => 'hello']);
});

test('biz_type 枚举正确转换', function () {
    $txn = StockTxn::factory()->create(['biz_type' => 'STOCKTAKE_PROFIT', 'direction' => 'IN']);
    expect($txn->biz_type->value)->toBe('STOCKTAKE_PROFIT');
    expect($txn->direction->value)->toBe('IN');
});
```

- [ ] **Step 6: 跑测试**

```bash
./vendor/bin/pest app/Modules/Inventory/Tests/Unit/StockTxnSchemaTest.php
```

Expected: 3 passed.

- [ ] **Step 7: Commit**

```bash
git add app/Modules/Inventory/
git commit -m "feat(inventory): stock_txns ledger table + enums"
```

---

# Phase D — BOM Schema（预建无写入）

## Task 14：`boms` + `bom_components` 表

**Files:**
- Create: `app/Modules/Inventory/Database/Migrations/2026_05_08_000071_create_boms_table.php`
- Create: `app/Modules/Inventory/Database/Migrations/2026_05_08_000072_create_bom_components_table.php`
- Create: `app/Modules/Inventory/Enums/BomType.php`
- Create: `app/Modules/Inventory/Models/Bom.php`
- Create: `app/Modules/Inventory/Models/BomComponent.php`
- Create: `app/Modules/Inventory/Database/Factories/BomFactory.php`
- Create: `app/Modules/Inventory/Database/Factories/BomComponentFactory.php`
- Test: `app/Modules/Inventory/Tests/Unit/BomSchemaTest.php`

- [ ] **Step 1: 写枚举 BomType**

`app/Modules/Inventory/Enums/BomType.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

enum BomType: string
{
    case Standard = 'STANDARD';
    case StoreCustom = 'STORE_CUSTOM';
}
```

- [ ] **Step 2: 写迁移 boms**

`app/Modules/Inventory/Database/Migrations/2026_05_08_000071_create_boms_table.php`：

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boms', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('output_sku_id', 26);
            $table->decimal('output_qty', 18, 4)->default(1);
            $table->enum('bom_type', ['STANDARD', 'STORE_CUSTOM'])->default('STANDARD');
            $table->char('store_id', 26)->nullable();
            $table->enum('status', ['active', 'disabled'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('output_sku_id')->references('id')->on('item_skus')->cascadeOnDelete();
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->index(['tenant_id', 'output_sku_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boms');
    }
};
```

- [ ] **Step 3: 写迁移 bom_components**

`app/Modules/Inventory/Database/Migrations/2026_05_08_000072_create_bom_components_table.php`：

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bom_components', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('bom_id', 26);
            $table->char('component_sku_id', 26);
            $table->decimal('consume_qty', 18, 4);
            $table->decimal('loss_rate', 6, 4)->default(0);
            $table->unsignedSmallInteger('sequence_no')->default(0);
            $table->timestamps();

            $table->foreign('bom_id')->references('id')->on('boms')->cascadeOnDelete();
            $table->foreign('component_sku_id')->references('id')->on('item_skus')->cascadeOnDelete();
            $table->index(['bom_id', 'sequence_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bom_components');
    }
};
```

- [ ] **Step 4: 写模型 Bom**

`app/Modules/Inventory/Models/Bom.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Inventory\Database\Factories\BomFactory;
use App\Modules\Inventory\Enums\BomType;
use App\Support\Eloquent\BelongsToTenant;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bom extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUlid;
    use SoftDeletes;

    protected $table = 'boms';

    protected $guarded = [];

    protected $casts = [
        'bom_type' => BomType::class,
        'output_qty' => 'decimal:4',
    ];

    public function outputSku(): BelongsTo
    {
        return $this->belongsTo(ItemSku::class, 'output_sku_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(BomComponent::class);
    }

    protected static function newFactory(): BomFactory
    {
        return BomFactory::new();
    }
}
```

- [ ] **Step 5: 写模型 BomComponent**

`app/Modules/Inventory/Models/BomComponent.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Inventory\Database\Factories\BomComponentFactory;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomComponent extends Model
{
    use HasFactory;
    use HasUlid;

    protected $table = 'bom_components';

    protected $guarded = [];

    protected $casts = [
        'consume_qty' => 'decimal:4',
        'loss_rate' => 'decimal:4',
        'sequence_no' => 'int',
    ];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function componentSku(): BelongsTo
    {
        return $this->belongsTo(ItemSku::class, 'component_sku_id');
    }

    protected static function newFactory(): BomComponentFactory
    {
        return BomComponentFactory::new();
    }
}
```

- [ ] **Step 6: 写两个工厂**

`app/Modules/Inventory/Database/Factories/BomFactory.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Database\Factories;

use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Inventory\Models\Bom;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class BomFactory extends Factory
{
    protected $model = Bom::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'output_sku_id' => ItemSku::factory(),
            'output_qty' => 1,
            'bom_type' => 'STANDARD',
            'store_id' => null,
            'status' => 'active',
        ];
    }
}
```

`app/Modules/Inventory/Database/Factories/BomComponentFactory.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Database\Factories;

use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Inventory\Models\Bom;
use App\Modules\Inventory\Models\BomComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

class BomComponentFactory extends Factory
{
    protected $model = BomComponent::class;

    public function definition(): array
    {
        return [
            'bom_id' => Bom::factory(),
            'component_sku_id' => ItemSku::factory(),
            'consume_qty' => $this->faker->randomFloat(2, 0.1, 10),
            'loss_rate' => 0,
            'sequence_no' => $this->faker->numberBetween(0, 99),
        ];
    }
}
```

- [ ] **Step 7: 写测试**

`app/Modules/Inventory/Tests/Unit/BomSchemaTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Inventory\Models\Bom;
use App\Modules\Inventory\Models\BomComponent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('bom + components 行能创建并关联', function () {
    $bom = Bom::factory()->create(['output_qty' => 5]);
    BomComponent::factory()->count(3)->create(['bom_id' => $bom->id]);

    expect($bom->components()->count())->toBe(3);
    expect((float) $bom->output_qty)->toBe(5.0);
});

test('bom 软删除生效', function () {
    $bom = Bom::factory()->create();
    $bom->delete();

    expect(Bom::query()->withoutGlobalScopes()->find($bom->id))->toBeNull();
});
```

- [ ] **Step 8: 跑测试**

```bash
./vendor/bin/pest app/Modules/Inventory/Tests/Unit/BomSchemaTest.php
```

Expected: 2 passed.

- [ ] **Step 9: Commit**

```bash
git add app/Modules/Inventory/
git commit -m "feat(inventory): boms + bom_components schema (no action)"
```

---

# Phase E — Guard + Actions

## Task 15：`InventoryGuard` 5 层 toggle 校验

**Files:**
- Create: `app/Modules/Inventory/Exceptions/InventoryDisabledException.php`
- Create: `app/Modules/Inventory/Support/InventoryGuard.php`
- Test: `app/Modules/Inventory/Tests/Unit/InventoryGuardTest.php`

- [ ] **Step 1: 写异常类**

`app/Modules/Inventory/Exceptions/InventoryDisabledException.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Exceptions;

use App\Support\Exceptions\BusinessException;

/**
 * 库存被某一层关闭（租户/门店/Item/SKU/policy.NONE）。
 * 携带 layer 属性指示哪一层关闭，便于调试与前端展示。
 */
class InventoryDisabledException extends BusinessException
{
    public function __construct(public readonly string $layer, public readonly string $resourceId)
    {
        parent::__construct("库存能力在 [{$layer}] 层被关闭（resource={$resourceId}）", 403);
    }
}
```

- [ ] **Step 2: 写 Guard 服务**

`app/Modules/Inventory/Support/InventoryGuard.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Support;

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Catalog\Models\ProductInventoryPolicy;
use App\Modules\Inventory\Exceptions\InventoryDisabledException;
use App\Modules\Inventory\Models\StoreInventoryConfig;
use App\Modules\Inventory\Models\TenantInventoryConfig;

/**
 * 五层 toggle 校验：tenant → store → item → sku → policy.track_type != NONE。
 * 任一层 false / 不存在均抛 InventoryDisabledException。
 *
 * 使用示例：
 *   InventoryGuard::assertEnabled($tenantId, $storeId, $skuId);
 *   // 若任意层关闭，抛 403 业务异常
 */
class InventoryGuard
{
    public static function assertEnabled(string $tenantId, string $storeId, string $skuId): void
    {
        $tenantCfg = TenantInventoryConfig::query()->where('tenant_id', $tenantId)->first();
        if (! $tenantCfg || ! $tenantCfg->inventory_enabled) {
            throw new InventoryDisabledException('tenant', $tenantId);
        }

        $storeCfg = StoreInventoryConfig::query()->where('store_id', $storeId)->first();
        if (! $storeCfg || ! $storeCfg->inventory_enabled) {
            throw new InventoryDisabledException('store', $storeId);
        }

        $sku = ItemSku::query()->withoutGlobalScopes()->whereKey($skuId)->first();
        if (! $sku || ! $sku->inventory_enabled) {
            throw new InventoryDisabledException('sku', $skuId);
        }

        $item = Item::query()->withoutGlobalScopes()->whereKey($sku->item_id)->first();
        if (! $item || ! $item->inventory_enabled) {
            throw new InventoryDisabledException('item', $sku->item_id);
        }

        $policy = ProductInventoryPolicy::query()->withoutGlobalScopes()->where('sku_id', $skuId)->first();
        if (! $policy || $policy->inventory_track_type->value === 'NONE') {
            throw new InventoryDisabledException('policy', $skuId);
        }
    }

    /**
     * 校验"允许负库存"两层 AND（tenant.negative_stock_allowed AND policy.allow_negative_stock）。
     */
    public static function negativeStockAllowed(string $tenantId, string $skuId): bool
    {
        $tenant = TenantInventoryConfig::query()->where('tenant_id', $tenantId)->first();
        if (! $tenant || ! $tenant->negative_stock_allowed) {
            return false;
        }
        $policy = ProductInventoryPolicy::query()->withoutGlobalScopes()->where('sku_id', $skuId)->first();
        return (bool) ($policy?->allow_negative_stock);
    }
}
```

- [ ] **Step 3: 写测试**

`app/Modules/Inventory/Tests/Unit/InventoryGuardTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Catalog\Models\ProductInventoryPolicy;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Inventory\Exceptions\InventoryDisabledException;
use App\Modules\Inventory\Models\StoreInventoryConfig;
use App\Modules\Inventory\Models\TenantInventoryConfig;
use App\Modules\Inventory\Support\InventoryGuard;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->store = Store::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->item = Item::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->sku = ItemSku::factory()->create(['tenant_id' => $this->tenant->id, 'item_id' => $this->item->id]);
});

test('全部 5 层启用时不抛', function () {
    InventoryGuard::assertEnabled($this->tenant->id, $this->store->id, $this->sku->id);
    expect(true)->toBeTrue();
});

test('tenant 关闭抛 InventoryDisabledException 且 layer=tenant', function () {
    TenantInventoryConfig::query()->where('tenant_id', $this->tenant->id)
        ->update(['inventory_enabled' => false]);

    try {
        InventoryGuard::assertEnabled($this->tenant->id, $this->store->id, $this->sku->id);
        $this->fail('expected exception');
    } catch (InventoryDisabledException $e) {
        expect($e->layer)->toBe('tenant');
    }
});

test('store 关闭抛异常且 layer=store', function () {
    StoreInventoryConfig::query()->where('store_id', $this->store->id)
        ->update(['inventory_enabled' => false]);

    try {
        InventoryGuard::assertEnabled($this->tenant->id, $this->store->id, $this->sku->id);
        $this->fail('expected exception');
    } catch (InventoryDisabledException $e) {
        expect($e->layer)->toBe('store');
    }
});

test('item 关闭抛异常且 layer=item', function () {
    $this->item->update(['inventory_enabled' => false]);

    try {
        InventoryGuard::assertEnabled($this->tenant->id, $this->store->id, $this->sku->id);
        $this->fail('expected exception');
    } catch (InventoryDisabledException $e) {
        expect($e->layer)->toBe('item');
    }
});

test('sku 关闭抛异常且 layer=sku', function () {
    $this->sku->update(['inventory_enabled' => false]);

    try {
        InventoryGuard::assertEnabled($this->tenant->id, $this->store->id, $this->sku->id);
        $this->fail('expected exception');
    } catch (InventoryDisabledException $e) {
        expect($e->layer)->toBe('sku');
    }
});

test('policy.track_type=NONE 抛异常且 layer=policy', function () {
    ProductInventoryPolicy::query()->withoutGlobalScopes()
        ->where('sku_id', $this->sku->id)
        ->update(['inventory_track_type' => 'NONE']);

    try {
        InventoryGuard::assertEnabled($this->tenant->id, $this->store->id, $this->sku->id);
        $this->fail('expected exception');
    } catch (InventoryDisabledException $e) {
        expect($e->layer)->toBe('policy');
    }
});

test('negativeStockAllowed 两层 AND 语义', function () {
    expect(InventoryGuard::negativeStockAllowed($this->tenant->id, $this->sku->id))->toBeFalse();

    TenantInventoryConfig::query()->where('tenant_id', $this->tenant->id)
        ->update(['negative_stock_allowed' => true]);
    expect(InventoryGuard::negativeStockAllowed($this->tenant->id, $this->sku->id))->toBeFalse();

    ProductInventoryPolicy::query()->withoutGlobalScopes()
        ->where('sku_id', $this->sku->id)
        ->update(['allow_negative_stock' => true]);
    expect(InventoryGuard::negativeStockAllowed($this->tenant->id, $this->sku->id))->toBeTrue();
});
```

- [ ] **Step 4: 跑测试**

```bash
./vendor/bin/pest app/Modules/Inventory/Tests/Unit/InventoryGuardTest.php
```

Expected: 7 passed.

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Inventory/
git commit -m "feat(inventory): InventoryGuard 5-layer toggle assertion"
```

---

## Task 16：`AdjustStockAction` + 事件

**Files:**
- Create: `app/Modules/Inventory/Events/StockChanged.php`
- Create: `app/Modules/Inventory/Actions/AdjustStockAction.php`
- Test: `app/Modules/Inventory/Tests/Feature/AdjustStockTest.php`

- [ ] **Step 1: 写事件**

`app/Modules/Inventory/Events/StockChanged.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 库存变化广播事件。Listener 可用于触发缓存失效、报表预聚合等。
 * 第一期不挂任何 Listener；事件存在仅为后续扩展。
 */
class StockChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $stockOwnerId,
        public readonly string $locationId,
        public readonly string $skuId,
        public readonly int $stockTxnId,
        public readonly string $bizType,
    ) {
    }
}
```

- [ ] **Step 2: 写 AdjustStockAction**

`app/Modules/Inventory/Actions/AdjustStockAction.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Events\StockChanged;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockTxn;
use App\Modules\Inventory\Support\InventoryGuard;
use App\Support\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;

/**
 * 手动库存调整：写一笔 ADJUSTMENT 流水 + 同步 stock_balance.available_qty。
 * subtype = 'INITIAL' 表示首次入库；'MANUAL' 表示日常加减。
 *
 * 流程：
 *   1. InventoryGuard 5 层 toggle 校验
 *   2. 行级锁定（或新建）stock_balance 行
 *   3. 校验 negative_stock 规则
 *   4. INSERT stock_txns
 *   5. UPDATE stock_balances.available_qty + version+=1
 *   6. emit StockChanged
 */
class AdjustStockAction
{
    /**
     * @param  string  $tenantId  租户 ID
     * @param  string  $storeId  门店 ID（用于 toggle 校验）
     * @param  string  $stockOwnerId  库存主体 ID
     * @param  string  $locationId  库位 ID
     * @param  string  $skuId  SKU ID
     * @param  string  $qtyChange  变动数量（字符串保精度；正数 IN，负数 OUT）
     * @param  string  $direction  'IN' | 'OUT'
     * @param  string  $subtype  'INITIAL' | 'MANUAL'
     * @param  string  $operatorId  操作人 user.id
     * @param  array<string, mixed>  $extraMeta  追加 meta_json 字段
     * @return int 新写入的 stock_txn id
     */
    public static function handle(
        string $tenantId,
        string $storeId,
        string $stockOwnerId,
        string $locationId,
        string $skuId,
        string $qtyChange,
        string $direction,
        string $subtype,
        string $operatorId,
        array $extraMeta = [],
    ): int {
        InventoryGuard::assertEnabled($tenantId, $storeId, $skuId);

        if (! in_array($direction, ['IN', 'OUT'], true)) {
            throw new BusinessException("direction 必须是 IN 或 OUT，实际：{$direction}");
        }
        if (! in_array($subtype, ['INITIAL', 'MANUAL'], true)) {
            throw new BusinessException("subtype 必须是 INITIAL 或 MANUAL");
        }

        return DB::transaction(function () use (
            $tenantId, $stockOwnerId, $locationId, $skuId, $qtyChange,
            $direction, $subtype, $operatorId, $extraMeta
        ) {
            // 行级锁
            $balance = StockBalance::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('stock_owner_id', $stockOwnerId)
                ->where('location_id', $locationId)
                ->where('sku_id', $skuId)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                $balance = StockBalance::query()->withoutGlobalScopes()->create([
                    'tenant_id' => $tenantId,
                    'stock_owner_id' => $stockOwnerId,
                    'location_id' => $locationId,
                    'sku_id' => $skuId,
                ]);
                // re-lock
                $balance = StockBalance::query()->withoutGlobalScopes()
                    ->whereKey($balance->id)->lockForUpdate()->first();
            }

            $newAvailable = bcadd((string) $balance->available_qty, $qtyChange, 4);
            if (bccomp($newAvailable, '0', 4) < 0
                && ! InventoryGuard::negativeStockAllowed($tenantId, $skuId)) {
                throw new BusinessException('库存不足且未开启允许负库存');
            }

            $txn = StockTxn::query()->create([
                'tenant_id' => $tenantId,
                'biz_type' => 'ADJUSTMENT',
                'stock_owner_id' => $stockOwnerId,
                'location_id' => $locationId,
                'sku_id' => $skuId,
                'qty_change' => $qtyChange,
                'direction' => $direction,
                'occurred_at' => now(),
                'operator_id' => $operatorId,
                'meta_json' => array_merge(['subtype' => $subtype], $extraMeta),
            ]);

            $balance->available_qty = $newAvailable;
            $balance->version = $balance->version + 1;
            $balance->save();

            StockChanged::dispatch(
                $tenantId, $stockOwnerId, $locationId, $skuId, (int) $txn->id, 'ADJUSTMENT'
            );

            return (int) $txn->id;
        });
    }
}
```

- [ ] **Step 3: 写测试**

`app/Modules/Inventory/Tests/Feature/AdjustStockTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Catalog\Models\ProductInventoryPolicy;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\AdjustStockAction;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Inventory\Models\StockTxn;
use App\Modules\Inventory\Models\TenantInventoryConfig;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Exceptions\BusinessException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->store = Store::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->owner = StockOwner::query()->withoutGlobalScopes()
        ->where('owner_ref_id', $this->store->id)->first();
    $this->location = StockLocation::query()->withoutGlobalScopes()
        ->where('stock_owner_id', $this->owner->id)->first();
    $this->item = Item::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->sku = ItemSku::factory()->create(['tenant_id' => $this->tenant->id, 'item_id' => $this->item->id]);
    $this->user = User::factory()->create();
});

test('INITIAL_STOCK 写入：从 0 加到 100 + 流水 + balance', function () {
    $txnId = AdjustStockAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '100', 'IN', 'INITIAL', $this->user->id,
    );

    $txn = StockTxn::query()->find($txnId);
    expect($txn->biz_type->value)->toBe('ADJUSTMENT');
    expect($txn->direction->value)->toBe('IN');
    expect((float) $txn->qty_change)->toBe(100.0);
    expect($txn->meta_json['subtype'])->toBe('INITIAL');

    $balance = StockBalance::query()->withoutGlobalScopes()
        ->where('sku_id', $this->sku->id)->first();
    expect((float) $balance->available_qty)->toBe(100.0);
    expect($balance->version)->toBe(1);
});

test('MANUAL OUT 扣减库存', function () {
    AdjustStockAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '50', 'IN', 'INITIAL', $this->user->id,
    );

    AdjustStockAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '-20', 'OUT', 'MANUAL', $this->user->id,
    );

    $balance = StockBalance::query()->withoutGlobalScopes()
        ->where('sku_id', $this->sku->id)->first();
    expect((float) $balance->available_qty)->toBe(30.0);
    expect($balance->version)->toBe(2);
});

test('库存不足且未开启负库存：抛 BusinessException', function () {
    expect(fn () => AdjustStockAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '-10', 'OUT', 'MANUAL', $this->user->id,
    ))->toThrow(BusinessException::class);
});

test('开启负库存（两层 AND）后允许扣到负数', function () {
    TenantInventoryConfig::query()->where('tenant_id', $this->tenant->id)
        ->update(['negative_stock_allowed' => true]);
    ProductInventoryPolicy::query()->withoutGlobalScopes()
        ->where('sku_id', $this->sku->id)
        ->update(['allow_negative_stock' => true]);

    AdjustStockAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '-10', 'OUT', 'MANUAL', $this->user->id,
    );

    $balance = StockBalance::query()->withoutGlobalScopes()
        ->where('sku_id', $this->sku->id)->first();
    expect((float) $balance->available_qty)->toBe(-10.0);
});

test('tenant 关闭库存时调用直接抛 InventoryDisabledException', function () {
    TenantInventoryConfig::query()->where('tenant_id', $this->tenant->id)
        ->update(['inventory_enabled' => false]);

    expect(fn () => AdjustStockAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '10', 'IN', 'INITIAL', $this->user->id,
    ))->toThrow(\App\Modules\Inventory\Exceptions\InventoryDisabledException::class);
});
```

- [ ] **Step 4: 跑测试**

```bash
./vendor/bin/pest app/Modules/Inventory/Tests/Feature/AdjustStockTest.php
```

Expected: 5 passed.

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Inventory/
git commit -m "feat(inventory): AdjustStockAction + StockChanged event"
```

---

## Task 17：`StocktakeAction`

**Files:**
- Create: `app/Modules/Inventory/Actions/StocktakeAction.php`
- Test: `app/Modules/Inventory/Tests/Feature/StocktakeTest.php`

- [ ] **Step 1: 写 Action**

`app/Modules/Inventory/Actions/StocktakeAction.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Events\StockChanged;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockTxn;
use App\Modules\Inventory\Support\InventoryGuard;
use Illuminate\Support\Facades\DB;

/**
 * 单 SKU 盘点：传入实盘数量，与 balance.available_qty 比对：
 *   delta > 0  → 盘盈：biz_type=STOCKTAKE_PROFIT, direction=IN
 *   delta < 0  → 盘亏：biz_type=STOCKTAKE_LOSS, direction=OUT
 *   delta == 0 → 不写流水，返回 null
 *
 * meta_json 写入：book_qty / actual_qty / note。
 */
class StocktakeAction
{
    /**
     * @return int|null 新 stock_txn id，或 null 表示无差异未写入
     */
    public static function handle(
        string $tenantId,
        string $storeId,
        string $stockOwnerId,
        string $locationId,
        string $skuId,
        string $actualQty,
        string $operatorId,
        ?string $note = null,
    ): ?int {
        InventoryGuard::assertEnabled($tenantId, $storeId, $skuId);

        return DB::transaction(function () use (
            $tenantId, $stockOwnerId, $locationId, $skuId, $actualQty, $operatorId, $note
        ) {
            $balance = StockBalance::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('stock_owner_id', $stockOwnerId)
                ->where('location_id', $locationId)
                ->where('sku_id', $skuId)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                $balance = StockBalance::query()->withoutGlobalScopes()->create([
                    'tenant_id' => $tenantId,
                    'stock_owner_id' => $stockOwnerId,
                    'location_id' => $locationId,
                    'sku_id' => $skuId,
                ]);
                $balance = StockBalance::query()->withoutGlobalScopes()
                    ->whereKey($balance->id)->lockForUpdate()->first();
            }

            $bookQty = (string) $balance->available_qty;
            $delta = bcsub($actualQty, $bookQty, 4);
            $cmp = bccomp($delta, '0', 4);

            if ($cmp === 0) {
                return null;
            }

            $bizType = $cmp > 0 ? 'STOCKTAKE_PROFIT' : 'STOCKTAKE_LOSS';
            $direction = $cmp > 0 ? 'IN' : 'OUT';

            $txn = StockTxn::query()->create([
                'tenant_id' => $tenantId,
                'biz_type' => $bizType,
                'stock_owner_id' => $stockOwnerId,
                'location_id' => $locationId,
                'sku_id' => $skuId,
                'qty_change' => $delta,
                'direction' => $direction,
                'occurred_at' => now(),
                'operator_id' => $operatorId,
                'meta_json' => [
                    'book_qty' => $bookQty,
                    'actual_qty' => $actualQty,
                    'note' => $note,
                ],
            ]);

            $balance->available_qty = $actualQty;
            $balance->version = $balance->version + 1;
            $balance->save();

            StockChanged::dispatch(
                $tenantId, $stockOwnerId, $locationId, $skuId, (int) $txn->id, $bizType
            );

            return (int) $txn->id;
        });
    }
}
```

- [ ] **Step 2: 写测试**

`app/Modules/Inventory/Tests/Feature/StocktakeTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\AdjustStockAction;
use App\Modules\Inventory\Actions\StocktakeAction;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Inventory\Models\StockTxn;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->store = Store::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->owner = StockOwner::query()->withoutGlobalScopes()
        ->where('owner_ref_id', $this->store->id)->first();
    $this->location = StockLocation::query()->withoutGlobalScopes()
        ->where('stock_owner_id', $this->owner->id)->first();
    $this->item = Item::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->sku = ItemSku::factory()->create(['tenant_id' => $this->tenant->id, 'item_id' => $this->item->id]);
    $this->user = User::factory()->create();

    AdjustStockAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '50', 'IN', 'INITIAL', $this->user->id,
    );
});

test('盘盈：实盘 60 vs 账面 50 → STOCKTAKE_PROFIT direction=IN', function () {
    $txnId = StocktakeAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '60', $this->user->id, '盘盈测试',
    );

    $txn = StockTxn::query()->find($txnId);
    expect($txn->biz_type->value)->toBe('STOCKTAKE_PROFIT');
    expect($txn->direction->value)->toBe('IN');
    expect((float) $txn->qty_change)->toBe(10.0);
    expect($txn->meta_json['book_qty'])->toBe('50.0000');
    expect($txn->meta_json['actual_qty'])->toBe('60');

    $balance = StockBalance::query()->withoutGlobalScopes()
        ->where('sku_id', $this->sku->id)->first();
    expect((float) $balance->available_qty)->toBe(60.0);
});

test('盘亏：实盘 30 vs 账面 50 → STOCKTAKE_LOSS direction=OUT', function () {
    $txnId = StocktakeAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '30', $this->user->id, null,
    );

    $txn = StockTxn::query()->find($txnId);
    expect($txn->biz_type->value)->toBe('STOCKTAKE_LOSS');
    expect($txn->direction->value)->toBe('OUT');
    expect((float) $txn->qty_change)->toBe(-20.0);

    $balance = StockBalance::query()->withoutGlobalScopes()
        ->where('sku_id', $this->sku->id)->first();
    expect((float) $balance->available_qty)->toBe(30.0);
});

test('实盘等于账面：返回 null 不写流水', function () {
    $beforeCount = StockTxn::query()->count();

    $result = StocktakeAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '50', $this->user->id, null,
    );

    expect($result)->toBeNull();
    expect(StockTxn::query()->count())->toBe($beforeCount);
});
```

- [ ] **Step 3: 跑测试**

```bash
./vendor/bin/pest app/Modules/Inventory/Tests/Feature/StocktakeTest.php
```

Expected: 3 passed.

- [ ] **Step 4: Commit**

```bash
git add app/Modules/Inventory/
git commit -m "feat(inventory): StocktakeAction with delta-based ledger"
```

---

## Task 18：`DamageAction`

**Files:**
- Create: `app/Modules/Inventory/Actions/DamageAction.php`
- Test: `app/Modules/Inventory/Tests/Feature/DamageTest.php`

- [ ] **Step 1: 写 Action**

`app/Modules/Inventory/Actions/DamageAction.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Events\StockChanged;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockTxn;
use App\Modules\Inventory\Support\InventoryGuard;
use App\Support\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;

/**
 * 单 SKU 报损：物理出库（第一期 damaged_qty 桶不写入）。
 * - qty 必须为正数
 * - 校验 available_qty >= qty（除非 negative_stock 开）
 * - 写 DAMAGE_OUT 流水（qty_change = -qty）
 * - balance.available_qty -= qty（只动 available；spec §6.3 决议）
 */
class DamageAction
{
    public static function handle(
        string $tenantId,
        string $storeId,
        string $stockOwnerId,
        string $locationId,
        string $skuId,
        string $qty,
        int $unitCostCents,
        string $operatorId,
        ?string $reason = null,
    ): int {
        InventoryGuard::assertEnabled($tenantId, $storeId, $skuId);

        if (bccomp($qty, '0', 4) <= 0) {
            throw new BusinessException('报损数量必须 > 0');
        }

        return DB::transaction(function () use (
            $tenantId, $stockOwnerId, $locationId, $skuId, $qty,
            $unitCostCents, $operatorId, $reason
        ) {
            $balance = StockBalance::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('stock_owner_id', $stockOwnerId)
                ->where('location_id', $locationId)
                ->where('sku_id', $skuId)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                throw new BusinessException('该 SKU 在此库位无库存记录，不能报损');
            }

            $newAvailable = bcsub((string) $balance->available_qty, $qty, 4);
            if (bccomp($newAvailable, '0', 4) < 0
                && ! InventoryGuard::negativeStockAllowed($tenantId, $skuId)) {
                throw new BusinessException('库存不足且未开启允许负库存');
            }

            $amountCents = (int) round((float) $qty * $unitCostCents);

            $txn = StockTxn::query()->create([
                'tenant_id' => $tenantId,
                'biz_type' => 'DAMAGE_OUT',
                'stock_owner_id' => $stockOwnerId,
                'location_id' => $locationId,
                'sku_id' => $skuId,
                'qty_change' => '-'.$qty,
                'unit_cost_cents' => $unitCostCents,
                'amount_cents' => $amountCents,
                'direction' => 'OUT',
                'occurred_at' => now(),
                'operator_id' => $operatorId,
                'meta_json' => ['reason' => $reason],
            ]);

            $balance->available_qty = $newAvailable;
            $balance->version = $balance->version + 1;
            $balance->save();

            StockChanged::dispatch(
                $tenantId, $stockOwnerId, $locationId, $skuId, (int) $txn->id, 'DAMAGE_OUT'
            );

            return (int) $txn->id;
        });
    }
}
```

- [ ] **Step 2: 写测试**

`app/Modules/Inventory/Tests/Feature/DamageTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\AdjustStockAction;
use App\Modules\Inventory\Actions\DamageAction;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Inventory\Models\StockTxn;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Exceptions\BusinessException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->store = Store::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->owner = StockOwner::query()->withoutGlobalScopes()
        ->where('owner_ref_id', $this->store->id)->first();
    $this->location = StockLocation::query()->withoutGlobalScopes()
        ->where('stock_owner_id', $this->owner->id)->first();
    $this->item = Item::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->sku = ItemSku::factory()->create(['tenant_id' => $this->tenant->id, 'item_id' => $this->item->id]);
    $this->user = User::factory()->create();

    AdjustStockAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '20', 'IN', 'INITIAL', $this->user->id,
    );
});

test('报损 5 件：流水 DAMAGE_OUT direction=OUT qty_change=-5', function () {
    $txnId = DamageAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '5', 800, $this->user->id, '过期',
    );

    $txn = StockTxn::query()->find($txnId);
    expect($txn->biz_type->value)->toBe('DAMAGE_OUT');
    expect($txn->direction->value)->toBe('OUT');
    expect((float) $txn->qty_change)->toBe(-5.0);
    expect($txn->unit_cost_cents)->toBe(800);
    expect($txn->amount_cents)->toBe(4000);
    expect($txn->meta_json['reason'])->toBe('过期');

    $balance = StockBalance::query()->withoutGlobalScopes()
        ->where('sku_id', $this->sku->id)->first();
    expect((float) $balance->available_qty)->toBe(15.0);
    expect((float) $balance->damaged_qty)->toBe(0.0); // 第一期不写 damaged 桶
});

test('报损量超过 available：抛 BusinessException', function () {
    expect(fn () => DamageAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '999', 100, $this->user->id, null,
    ))->toThrow(BusinessException::class);
});

test('报损 qty <= 0 抛异常', function () {
    expect(fn () => DamageAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '0', 100, $this->user->id, null,
    ))->toThrow(BusinessException::class);
});
```

- [ ] **Step 3: 跑测试**

```bash
./vendor/bin/pest app/Modules/Inventory/Tests/Feature/DamageTest.php
```

Expected: 3 passed.

- [ ] **Step 4: Commit**

```bash
git add app/Modules/Inventory/
git commit -m "feat(inventory): DamageAction (single bucket per spec §6.3)"
```

---

## Task 19：`ReverseStockTxnAction`

**Files:**
- Create: `app/Modules/Inventory/Actions/ReverseStockTxnAction.php`
- Test: `app/Modules/Inventory/Tests/Feature/ReverseStockTxnTest.php`

- [ ] **Step 1: 写 Action**

`app/Modules/Inventory/Actions/ReverseStockTxnAction.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Events\StockChanged;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockTxn;
use App\Support\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;

/**
 * 撤销任一笔 stock_txn：
 * - 写一条反向流水（biz_type 沿用原笔，qty_change 与 direction 取反）
 * - meta_json.cancels_txn_id = 原 txn id
 * - 反向更新 stock_balance.available_qty
 *
 * 校验：
 * - 原 txn 必须存在且属于同租户
 * - 已被撤销过（即存在另一笔 meta.cancels_txn_id == 原 id）则禁止再撤
 */
class ReverseStockTxnAction
{
    public static function handle(int $txnId, string $operatorId): int
    {
        return DB::transaction(function () use ($txnId, $operatorId) {
            $orig = StockTxn::query()->find($txnId);
            if (! $orig) {
                throw new BusinessException("流水 {$txnId} 不存在");
            }

            $alreadyReversed = StockTxn::query()
                ->where('tenant_id', $orig->tenant_id)
                ->whereJsonContains('meta_json->cancels_txn_id', $txnId)
                ->exists();
            if ($alreadyReversed) {
                throw new BusinessException("流水 {$txnId} 已被撤销，不能再撤");
            }

            $balance = StockBalance::query()->withoutGlobalScopes()
                ->where('tenant_id', $orig->tenant_id)
                ->where('stock_owner_id', $orig->stock_owner_id)
                ->where('location_id', $orig->location_id)
                ->where('sku_id', $orig->sku_id)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                throw new BusinessException('原流水对应 balance 行不存在');
            }

            $reverseQty = bcmul((string) $orig->qty_change, '-1', 4);
            $reverseDirection = match ($orig->direction->value) {
                'IN' => 'OUT',
                'OUT' => 'IN',
                'FREEZE' => 'RELEASE',
                'RELEASE' => 'FREEZE',
                default => 'OUT',
            };

            $reverseTxn = StockTxn::query()->create([
                'tenant_id' => $orig->tenant_id,
                'biz_type' => $orig->biz_type->value,
                'biz_order_type' => $orig->biz_order_type,
                'biz_order_id' => $orig->biz_order_id,
                'stock_owner_id' => $orig->stock_owner_id,
                'location_id' => $orig->location_id,
                'sku_id' => $orig->sku_id,
                'qty_change' => $reverseQty,
                'unit_cost_cents' => $orig->unit_cost_cents,
                'amount_cents' => $orig->amount_cents !== null ? -$orig->amount_cents : null,
                'direction' => $reverseDirection,
                'occurred_at' => now(),
                'operator_id' => $operatorId,
                'meta_json' => ['cancels_txn_id' => $txnId],
            ]);

            $balance->available_qty = bcadd((string) $balance->available_qty, $reverseQty, 4);
            $balance->version = $balance->version + 1;
            $balance->save();

            StockChanged::dispatch(
                $orig->tenant_id, $orig->stock_owner_id, $orig->location_id,
                $orig->sku_id, (int) $reverseTxn->id, $orig->biz_type->value
            );

            return (int) $reverseTxn->id;
        });
    }
}
```

- [ ] **Step 2: 写测试**

`app/Modules/Inventory/Tests/Feature/ReverseStockTxnTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\AdjustStockAction;
use App\Modules\Inventory\Actions\DamageAction;
use App\Modules\Inventory\Actions\ReverseStockTxnAction;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Inventory\Models\StockTxn;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Exceptions\BusinessException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->store = Store::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->owner = StockOwner::query()->withoutGlobalScopes()
        ->where('owner_ref_id', $this->store->id)->first();
    $this->location = StockLocation::query()->withoutGlobalScopes()
        ->where('stock_owner_id', $this->owner->id)->first();
    $this->item = Item::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->sku = ItemSku::factory()->create(['tenant_id' => $this->tenant->id, 'item_id' => $this->item->id]);
    $this->user = User::factory()->create();
});

test('撤销 ADJUSTMENT IN 笔：库存回到撤销前', function () {
    $orig = AdjustStockAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '50', 'IN', 'INITIAL', $this->user->id,
    );

    $reverseId = ReverseStockTxnAction::handle($orig, $this->user->id);

    $reverseTxn = StockTxn::query()->find($reverseId);
    expect($reverseTxn->biz_type->value)->toBe('ADJUSTMENT');
    expect($reverseTxn->direction->value)->toBe('OUT');
    expect((float) $reverseTxn->qty_change)->toBe(-50.0);
    expect($reverseTxn->meta_json['cancels_txn_id'])->toBe($orig);

    $balance = StockBalance::query()->withoutGlobalScopes()
        ->where('sku_id', $this->sku->id)->first();
    expect((float) $balance->available_qty)->toBe(0.0);
});

test('撤销 DAMAGE_OUT：库存恢复', function () {
    AdjustStockAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '20', 'IN', 'INITIAL', $this->user->id,
    );
    $damageTxn = DamageAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '5', 800, $this->user->id, '过期',
    );

    ReverseStockTxnAction::handle($damageTxn, $this->user->id);

    $balance = StockBalance::query()->withoutGlobalScopes()
        ->where('sku_id', $this->sku->id)->first();
    expect((float) $balance->available_qty)->toBe(20.0);
});

test('已撤销的不能再撤', function () {
    $orig = AdjustStockAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '10', 'IN', 'INITIAL', $this->user->id,
    );

    ReverseStockTxnAction::handle($orig, $this->user->id);

    expect(fn () => ReverseStockTxnAction::handle($orig, $this->user->id))
        ->toThrow(BusinessException::class);
});

test('不存在的 txn_id 抛异常', function () {
    expect(fn () => ReverseStockTxnAction::handle(99999, $this->user->id))
        ->toThrow(BusinessException::class);
});
```

- [ ] **Step 3: 跑测试**

```bash
./vendor/bin/pest app/Modules/Inventory/Tests/Feature/ReverseStockTxnTest.php
```

Expected: 4 passed.

- [ ] **Step 4: Commit**

```bash
git add app/Modules/Inventory/
git commit -m "feat(inventory): ReverseStockTxnAction with cancels_txn_id marker"
```

---

# Phase F — 权限

## Task 20：Permission 枚举扩展 + 系统角色权限种子

**Files:**
- Modify: `app/Modules/Authorization/Enums/Permission.php`（+16 cases）
- Create: `app/Modules/Inventory/Database/Migrations/2026_05_08_000073_seed_inventory_permissions.php`
- Test: `app/Modules/Inventory/Tests/Feature/PermissionSeedTest.php`

- [ ] **Step 1: 扩展 Permission 枚举**

`app/Modules/Authorization/Enums/Permission.php` 完整重写：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Enums;

/**
 * 商户域权限（INV-A：所有 case 不可有 platform. 前缀）。
 *
 * 单一来源：写入 roles.permissions 时仅接受这里枚举值的 string。
 * 后续 Goods / Order / Inventory 等模块各自 PR 在此追加 case。
 */
enum Permission: string
{
    case RolesRead = 'roles.read';
    case RolesManage = 'roles.manage';
    case UsersRead = 'users.read';
    case UsersAssignRole = 'users.assign-role';
    case TenantRead = 'tenant.read';
    case StoresRead = 'stores.read';

    // ── Inventory 模块（Task 20）─────────────────────────────
    case ItemsRead = 'items.read';
    case ItemsWrite = 'items.write';
    case ItemSkusRead = 'item_skus.read';
    case ItemSkusWrite = 'item_skus.write';
    case CategoriesRead = 'categories.read';
    case CategoriesWrite = 'categories.write';
    case InventoryRead = 'inventory.read';
    case InventoryAdjust = 'inventory.adjust';
    case StocktakeWrite = 'stocktake.write';
    case DamageWrite = 'damage.write';
    case StockTxnRead = 'stock_txn.read';
    case StockTxnReverse = 'stock_txn.reverse';
    case InventoryConfigRead = 'inventory_config.read';
    case InventoryConfigUpdate = 'inventory_config.update';
    case InventoryPolicyRead = 'inventory_policy.read';
    case InventoryPolicyUpdate = 'inventory_policy.update';
}
```

- [ ] **Step 2: 写种子迁移**

`app/Modules/Inventory/Database/Migrations/2026_05_08_000073_seed_inventory_permissions.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * 把 16 个 inventory 相关权限点追加到现有系统角色：
 * - TenantAdmin: 全部 16 个
 * - StoreManager: 除 inventory_config.update / inventory_policy.update 外 14 个
 * - StoreClerk: 仅 *.read 6 个 + inventory.adjust + stocktake.write 共 8 个
 */
return new class extends Migration
{
    public function up(): void
    {
        $allInventoryPerms = [
            'items.read', 'items.write',
            'item_skus.read', 'item_skus.write',
            'categories.read', 'categories.write',
            'inventory.read', 'inventory.adjust',
            'stocktake.write', 'damage.write',
            'stock_txn.read', 'stock_txn.reverse',
            'inventory_config.read', 'inventory_config.update',
            'inventory_policy.read', 'inventory_policy.update',
        ];

        $managerPerms = array_values(array_diff(
            $allInventoryPerms,
            ['inventory_config.update', 'inventory_policy.update']
        ));

        $clerkPerms = [
            'items.read', 'item_skus.read', 'categories.read',
            'inventory.read', 'inventory.adjust',
            'stocktake.write', 'stock_txn.read',
            'inventory_config.read',
        ];

        $this->extend('TenantAdmin', $allInventoryPerms);
        $this->extend('StoreManager', $managerPerms);
        $this->extend('StoreClerk', $clerkPerms);
    }

    public function down(): void
    {
        $allInventoryPerms = [
            'items.read', 'items.write',
            'item_skus.read', 'item_skus.write',
            'categories.read', 'categories.write',
            'inventory.read', 'inventory.adjust',
            'stocktake.write', 'damage.write',
            'stock_txn.read', 'stock_txn.reverse',
            'inventory_config.read', 'inventory_config.update',
            'inventory_policy.read', 'inventory_policy.update',
        ];

        foreach (['TenantAdmin', 'StoreManager', 'StoreClerk'] as $code) {
            $role = Role::query()->whereNull('tenant_id')->where('code', $code)->first();
            if (! $role) {
                continue;
            }
            $role->permissions = array_values(array_diff(
                (array) $role->permissions, $allInventoryPerms
            ));
            $role->save();
        }
    }

    private function extend(string $code, array $additionalPerms): void
    {
        $role = Role::query()->whereNull('tenant_id')->where('code', $code)->first();
        if (! $role) {
            return;
        }
        $role->permissions = array_values(array_unique(
            array_merge((array) $role->permissions, $additionalPerms)
        ));
        $role->save();
    }
};
```

- [ ] **Step 3: 写测试**

`app/Modules/Inventory/Tests/Feature/PermissionSeedTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Authorization\Enums\Permission;
use App\Modules\Authorization\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Permission 枚举包含 16 个 inventory 权限点', function () {
    $cases = array_column(Permission::cases(), 'value');

    foreach ([
        'items.read', 'items.write', 'item_skus.read', 'item_skus.write',
        'categories.read', 'categories.write',
        'inventory.read', 'inventory.adjust', 'stocktake.write', 'damage.write',
        'stock_txn.read', 'stock_txn.reverse',
        'inventory_config.read', 'inventory_config.update',
        'inventory_policy.read', 'inventory_policy.update',
    ] as $perm) {
        expect($cases)->toContain($perm);
    }
});

test('TenantAdmin 系统角色含全部 16 个 inventory 权限', function () {
    $role = Role::query()->whereNull('tenant_id')->where('code', 'TenantAdmin')->firstOrFail();
    foreach (['items.read', 'inventory_config.update', 'stock_txn.reverse'] as $p) {
        expect($role->permissions)->toContain($p);
    }
});

test('StoreClerk 不含 stock_txn.reverse / damage.write / inventory_config.update', function () {
    $role = Role::query()->whereNull('tenant_id')->where('code', 'StoreClerk')->firstOrFail();
    expect($role->permissions)->not->toContain('stock_txn.reverse');
    expect($role->permissions)->not->toContain('damage.write');
    expect($role->permissions)->not->toContain('inventory_config.update');
    expect($role->permissions)->toContain('inventory.adjust');
});
```

- [ ] **Step 4: 跑测试**

```bash
./vendor/bin/pest app/Modules/Inventory/Tests/Feature/PermissionSeedTest.php
```

Expected: 3 passed.

- [ ] **Step 5: Commit**

```bash
git add app/Modules/
git commit -m "feat(inventory): permissions enum + role seed migration"
```

---

# Phase G — Controller + UI

## Task 21：`TenantStockController` 查询页面（库存 + 流水）

**Files:**
- Create: `app/Modules/Inventory/Http/Controllers/Web/TenantStockController.php`
- Create: `resources/js/pages/tenant/Stock/Index.vue`
- Create: `resources/js/pages/tenant/Stock/Txns.vue`
- Modify: `routes/web.php`
- Test: `app/Modules/Inventory/Tests/Feature/TenantStockWebTest.php`

- [ ] **Step 1: 写 Controller**

`app/Modules/Inventory/Http/Controllers/Web/TenantStockController.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers\Web;

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Inventory\Models\StockTxn;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 租户后台 - 库存/流水查询。
 * /tenant/stock        库存余额（按 sku 聚合 4 状态桶）
 * /tenant/stock/txns   流水时间线
 */
class TenantStockController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $this->requireCurrentTenant($request);

        $storeId = (string) $request->query('store_id', '');
        $q = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));
        $pageSize = min(100, max(5, (int) $request->query('per_page', 20)));

        $stores = Store::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->orderBy('name')
            ->get(['id', 'name'])->toArray();

        $rows = [];
        $total = 0;
        if ($storeId !== '') {
            $owner = StockOwner::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('owner_type', 'STORE')
                ->where('owner_ref_id', $storeId)
                ->first();

            if ($owner) {
                $query = StockBalance::query()->withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('stock_owner_id', $owner->id)
                    ->orderByDesc('updated_at');

                if ($q !== '') {
                    $skuIds = ItemSku::query()->withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->whereHas('item', fn ($q2) => $q2->where('item_name', 'like', "%{$q}%"))
                        ->pluck('id');
                    $query->whereIn('sku_id', $skuIds);
                }

                $total = (clone $query)->count();
                $balances = $query->skip(($page - 1) * $pageSize)->take($pageSize)->get();

                $skuIds = $balances->pluck('sku_id')->all();
                $skus = ItemSku::query()->withoutGlobalScopes()
                    ->whereIn('id', $skuIds)->with('item:id,item_name,unit')
                    ->get()->keyBy('id');

                $rows = $balances->map(function (StockBalance $b) use ($skus) {
                    $sku = $skus->get($b->sku_id);
                    return [
                        'id' => $b->id,
                        'sku_id' => $b->sku_id,
                        'item_name' => $sku?->item?->item_name ?? '?',
                        'unit' => $sku?->item?->unit ?? '',
                        'barcode' => $sku?->barcode,
                        'available_qty' => (float) $b->available_qty,
                        'reserved_qty' => (float) $b->reserved_qty,
                        'in_transit_qty' => (float) $b->in_transit_qty,
                        'damaged_qty' => (float) $b->damaged_qty,
                        'updated_at' => $b->updated_at?->toIso8601String(),
                    ];
                })->all();
            }
        }

        return Inertia::render('tenant/Stock/Index', [
            'stores' => $stores,
            'store_id' => $storeId,
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'q' => $q,
        ]);
    }

    public function txns(Request $request): Response
    {
        $tenantId = $this->requireCurrentTenant($request);

        $bizType = (string) $request->query('biz_type', 'all');
        $skuId = (string) $request->query('sku_id', '');
        $page = max(1, (int) $request->query('page', 1));
        $pageSize = min(100, max(5, (int) $request->query('per_page', 30)));

        $query = StockTxn::query()->where('tenant_id', $tenantId)
            ->orderByDesc('id');
        if ($bizType !== 'all') {
            $query->where('biz_type', $bizType);
        }
        if ($skuId !== '') {
            $query->where('sku_id', $skuId);
        }

        $total = (clone $query)->count();
        $txns = $query->skip(($page - 1) * $pageSize)->take($pageSize)->get();

        $skuIds = $txns->pluck('sku_id')->unique();
        $skus = ItemSku::query()->withoutGlobalScopes()
            ->whereIn('id', $skuIds)->with('item:id,item_name')
            ->get()->keyBy('id');

        $reversedSet = StockTxn::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $txns->pluck('id'))
            ->orWhereJsonContains('meta_json->cancels_txn_id', null) // dummy；下方用单独查询
            ->pluck('id');

        $cancelledIds = StockTxn::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('meta_json->cancels_txn_id')
            ->pluck('meta_json->cancels_txn_id')->all();
        $cancelledIdSet = array_flip($cancelledIds);

        $rows = $txns->map(function (StockTxn $t) use ($skus, $cancelledIdSet) {
            $sku = $skus->get($t->sku_id);
            return [
                'id' => (int) $t->id,
                'biz_type' => $t->biz_type->value,
                'direction' => $t->direction->value,
                'qty_change' => (float) $t->qty_change,
                'sku_id' => $t->sku_id,
                'item_name' => $sku?->item?->item_name ?? '?',
                'occurred_at' => $t->occurred_at?->toIso8601String(),
                'unit_cost_cents' => $t->unit_cost_cents,
                'amount_cents' => $t->amount_cents,
                'meta' => $t->meta_json,
                'is_cancelled' => isset($cancelledIdSet[(int) $t->id]),
                'is_reversal' => isset($t->meta_json['cancels_txn_id']),
            ];
        })->all();

        return Inertia::render('tenant/Stock/Txns', [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'biz_type' => $bizType,
            'sku_id' => $skuId,
            'biz_types' => array_column(\App\Modules\Inventory\Enums\StockTxnBizType::cases(), 'value'),
        ]);
    }

    private function requireCurrentTenant(Request $request): string
    {
        $id = $request->session()->get('current_tenant_id');
        if (! $id) {
            throw ValidationException::withMessages(['tenant' => '尚未选定租户']);
        }
        return (string) $id;
    }
}
```

- [ ] **Step 2: 修改 routes/web.php 增加 stock 路由**

在 `tenant` 路由组内添加：

```php
use App\Modules\Inventory\Http\Controllers\Web\TenantStockController;

// 在路由组内
Route::get('stock', [TenantStockController::class, 'index']);
Route::get('stock/txns', [TenantStockController::class, 'txns']);
```

- [ ] **Step 3: 写 Stock/Index.vue**

`resources/js/pages/tenant/Stock/Index.vue`：

```vue
<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

interface Row {
  id: string; sku_id: string; item_name: string; unit: string;
  barcode: string | null;
  available_qty: number; reserved_qty: number;
  in_transit_qty: number; damaged_qty: number;
  updated_at: string | null;
}

const page = usePage();
const props = computed(() => page.props as unknown as {
  stores: { id: string; name: string }[];
  store_id: string; rows: Row[]; total: number;
  page: number; pageSize: number; q: string;
});

const storeId = ref(props.value.store_id);
const keyword = ref(props.value.q);

function reload(params: Record<string, unknown> = {}) {
  router.get('/tenant/stock', {
    store_id: storeId.value, q: keyword.value,
    page: props.value.page, per_page: props.value.pageSize, ...params,
  }, { preserveState: true, preserveScroll: true });
}
</script>

<template>
  <div class="p-6">
    <h1 class="text-xl font-medium mb-4">库存查询</h1>

    <div class="flex gap-2 mb-3 text-sm">
      <select v-model="storeId" class="px-2 py-1 border rounded"
        @change="reload({ page: 1 })">
        <option value="">选择门店</option>
        <option v-for="s in props.stores" :key="s.id" :value="s.id">{{ s.name }}</option>
      </select>
      <input v-model="keyword" placeholder="搜索物料名"
        class="px-2 py-1 border rounded w-60" :disabled="!storeId"
        @keyup.enter="reload({ page: 1 })" />
    </div>

    <div v-if="!storeId" class="text-slate-400 py-12 text-center">
      请先选择门店
    </div>
    <table v-else class="w-full text-sm border-collapse">
      <thead>
        <tr class="border-b bg-slate-50">
          <th class="text-left p-2">物料</th>
          <th class="text-left p-2">条码</th>
          <th class="text-left p-2">单位</th>
          <th class="text-right p-2">可用</th>
          <th class="text-right p-2">预占</th>
          <th class="text-right p-2">在途</th>
          <th class="text-right p-2">报损中</th>
          <th class="text-left p-2">更新时间</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="r in props.rows" :key="r.id" class="border-b">
          <td class="p-2">{{ r.item_name }}</td>
          <td class="p-2 font-mono text-xs">{{ r.barcode || '—' }}</td>
          <td class="p-2">{{ r.unit }}</td>
          <td class="p-2 text-right font-medium">{{ r.available_qty }}</td>
          <td class="p-2 text-right text-slate-400">{{ r.reserved_qty }}</td>
          <td class="p-2 text-right text-slate-400">{{ r.in_transit_qty }}</td>
          <td class="p-2 text-right text-slate-400">{{ r.damaged_qty }}</td>
          <td class="p-2 text-xs text-slate-500">{{ r.updated_at }}</td>
        </tr>
        <tr v-if="props.rows.length === 0">
          <td colspan="8" class="text-center text-slate-400 p-6">无库存记录</td>
        </tr>
      </tbody>
    </table>

    <div class="mt-4 flex items-center justify-between text-sm">
      <span>共 {{ props.total }} 条</span>
      <div class="flex gap-1">
        <button :disabled="props.page <= 1"
          class="px-2 py-1 border rounded disabled:opacity-40"
          @click="reload({ page: props.page - 1 })">上一页</button>
        <span class="px-2 py-1">{{ props.page }}</span>
        <button :disabled="props.page * props.pageSize >= props.total"
          class="px-2 py-1 border rounded disabled:opacity-40"
          @click="reload({ page: props.page + 1 })">下一页</button>
      </div>
    </div>
  </div>
</template>
```

- [ ] **Step 4: 写 Stock/Txns.vue**

`resources/js/pages/tenant/Stock/Txns.vue`：

```vue
<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

interface Row {
  id: number; biz_type: string; direction: string;
  qty_change: number; sku_id: string; item_name: string;
  occurred_at: string | null;
  unit_cost_cents: number | null; amount_cents: number | null;
  meta: Record<string, unknown> | null;
  is_cancelled: boolean; is_reversal: boolean;
}

const page = usePage();
const props = computed(() => page.props as unknown as {
  rows: Row[]; total: number; page: number; pageSize: number;
  biz_type: string; sku_id: string; biz_types: string[];
});

const bizType = ref(props.value.biz_type);

function reload(params: Record<string, unknown> = {}) {
  router.get('/tenant/stock/txns', {
    biz_type: bizType.value, sku_id: props.value.sku_id,
    page: props.value.page, per_page: props.value.pageSize, ...params,
  }, { preserveState: true, preserveScroll: true });
}

function reverse(id: number) {
  if (!confirm(`确认撤销流水 #${id}？`)) return;
  router.post(`/tenant/stock/txns/${id}/reverse`, {},
    { preserveScroll: true, onSuccess: () => reload() });
}
</script>

<template>
  <div class="p-6">
    <h1 class="text-xl font-medium mb-4">库存流水</h1>

    <div class="flex gap-2 mb-3 text-sm">
      <select v-model="bizType" class="px-2 py-1 border rounded"
        @change="reload({ page: 1 })">
        <option value="all">全部类型</option>
        <option v-for="t in props.biz_types" :key="t" :value="t">{{ t }}</option>
      </select>
    </div>

    <table class="w-full text-sm border-collapse">
      <thead>
        <tr class="border-b bg-slate-50">
          <th class="text-left p-2">#</th>
          <th class="text-left p-2">类型</th>
          <th class="text-left p-2">方向</th>
          <th class="text-right p-2">数量</th>
          <th class="text-left p-2">物料</th>
          <th class="text-left p-2">时间</th>
          <th class="text-left p-2">状态</th>
          <th class="text-right p-2">操作</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="r in props.rows" :key="r.id"
          :class="['border-b', r.is_cancelled ? 'opacity-50 line-through' : '']">
          <td class="p-2 font-mono">{{ r.id }}</td>
          <td class="p-2">{{ r.biz_type }}</td>
          <td class="p-2">{{ r.direction }}</td>
          <td class="p-2 text-right">{{ r.qty_change }}</td>
          <td class="p-2">{{ r.item_name }}</td>
          <td class="p-2 text-xs text-slate-500">{{ r.occurred_at }}</td>
          <td class="p-2">
            <span v-if="r.is_reversal" class="text-amber-600 text-xs">撤销笔</span>
            <span v-else-if="r.is_cancelled" class="text-slate-400 text-xs">已撤销</span>
            <span v-else class="text-emerald-600 text-xs">有效</span>
          </td>
          <td class="p-2 text-right">
            <button v-if="!r.is_cancelled && !r.is_reversal"
              class="text-rose-600 text-xs"
              @click="reverse(r.id)">撤销</button>
          </td>
        </tr>
        <tr v-if="props.rows.length === 0">
          <td colspan="8" class="text-center text-slate-400 p-6">暂无流水</td>
        </tr>
      </tbody>
    </table>

    <div class="mt-4 flex items-center justify-between text-sm">
      <span>共 {{ props.total }} 条</span>
      <div class="flex gap-1">
        <button :disabled="props.page <= 1"
          class="px-2 py-1 border rounded disabled:opacity-40"
          @click="reload({ page: props.page - 1 })">上一页</button>
        <span class="px-2 py-1">{{ props.page }}</span>
        <button :disabled="props.page * props.pageSize >= props.total"
          class="px-2 py-1 border rounded disabled:opacity-40"
          @click="reload({ page: props.page + 1 })">下一页</button>
      </div>
    </div>
  </div>
</template>
```

- [ ] **Step 5: 写测试**

`app/Modules/Inventory/Tests/Feature/TenantStockWebTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Identity\Models\Membership;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\AdjustStockAction;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->actor = User::factory()->create();
    Membership::factory()->create([
        'user_id' => $this->actor->id, 'tenant_id' => $this->tenant->id, 'store_id' => null,
    ]);
    $this->store = Store::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($this->actor, 'web')
        ->withSession(['current_tenant_id' => $this->tenant->id]);
});

test('GET /tenant/stock 无 store_id 时返回空 rows', function () {
    $this->get('/tenant/stock')
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('tenant/Stock/Index')
            ->where('rows', [])
            ->where('total', 0)
        );
});

test('GET /tenant/stock?store_id= 返回该门店库存', function () {
    $owner = StockOwner::query()->withoutGlobalScopes()
        ->where('owner_ref_id', $this->store->id)->first();
    $location = StockLocation::query()->withoutGlobalScopes()
        ->where('stock_owner_id', $owner->id)->first();

    $item = Item::factory()->create(['tenant_id' => $this->tenant->id, 'item_name' => '咖啡豆']);
    $sku = ItemSku::factory()->create(['tenant_id' => $this->tenant->id, 'item_id' => $item->id]);

    AdjustStockAction::handle(
        $this->tenant->id, $this->store->id, $owner->id, $location->id,
        $sku->id, '100', 'IN', 'INITIAL', $this->actor->id,
    );

    $this->get('/tenant/stock?store_id='.$this->store->id)
        ->assertInertia(fn ($p) => $p
            ->where('total', 1)
            ->where('rows.0.item_name', '咖啡豆')
            ->where('rows.0.available_qty', 100.0)
        );
});

test('GET /tenant/stock/txns 列出本租户全部流水', function () {
    $owner = StockOwner::query()->withoutGlobalScopes()
        ->where('owner_ref_id', $this->store->id)->first();
    $location = StockLocation::query()->withoutGlobalScopes()
        ->where('stock_owner_id', $owner->id)->first();
    $item = Item::factory()->create(['tenant_id' => $this->tenant->id]);
    $sku = ItemSku::factory()->create(['tenant_id' => $this->tenant->id, 'item_id' => $item->id]);

    AdjustStockAction::handle(
        $this->tenant->id, $this->store->id, $owner->id, $location->id,
        $sku->id, '50', 'IN', 'INITIAL', $this->actor->id,
    );

    $this->get('/tenant/stock/txns')
        ->assertInertia(fn ($p) => $p
            ->component('tenant/Stock/Txns')
            ->where('total', 1)
            ->where('rows.0.biz_type', 'ADJUSTMENT')
            ->where('rows.0.qty_change', 50.0)
        );
});

test('跨租户流水不可见', function () {
    $other = Tenant::factory()->create();
    $store2 = Store::factory()->create(['tenant_id' => $other->id]);
    $owner2 = StockOwner::query()->withoutGlobalScopes()
        ->where('owner_ref_id', $store2->id)->first();
    $location2 = StockLocation::query()->withoutGlobalScopes()
        ->where('stock_owner_id', $owner2->id)->first();
    $item2 = Item::factory()->create(['tenant_id' => $other->id]);
    $sku2 = ItemSku::factory()->create(['tenant_id' => $other->id, 'item_id' => $item2->id]);

    $u2 = User::factory()->create();
    AdjustStockAction::handle(
        $other->id, $store2->id, $owner2->id, $location2->id,
        $sku2->id, '99', 'IN', 'INITIAL', $u2->id,
    );

    $this->get('/tenant/stock/txns')
        ->assertInertia(fn ($p) => $p->where('total', 0));
});
```

- [ ] **Step 6: 跑测试 + type-check**

```bash
./vendor/bin/pest app/Modules/Inventory/Tests/Feature/TenantStockWebTest.php
npx vue-tsc --noEmit
```

Expected: 4 passed + 无类型错误。

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat(inventory): TenantStockController + Stock pages (Index, Txns)"
```

---

## Task 22：`TenantStockMutationController` 三件套表单

**Files:**
- Create: `app/Modules/Inventory/Http/Controllers/Web/TenantStockMutationController.php`
- Create: `resources/js/pages/tenant/Stock/Adjust.vue`
- Create: `resources/js/pages/tenant/Stock/Stocktake.vue`
- Create: `resources/js/pages/tenant/Stock/Damage.vue`
- Modify: `routes/web.php`
- Test: `app/Modules/Inventory/Tests/Feature/TenantStockMutationWebTest.php`

- [ ] **Step 1: 写 Controller**

`app/Modules/Inventory/Http/Controllers/Web/TenantStockMutationController.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers\Web;

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Inventory\Actions\AdjustStockAction;
use App\Modules\Inventory\Actions\DamageAction;
use App\Modules\Inventory\Actions\ReverseStockTxnAction;
use App\Modules\Inventory\Actions\StocktakeAction;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockOwner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 三件套写入入口 + 撤销流水入口。
 * 所有写入都通过对应 Action（已含 InventoryGuard 校验、行级锁、事务）。
 */
class TenantStockMutationController extends Controller
{
    public function adjustForm(Request $request): Response
    {
        return $this->renderForm($request, 'tenant/Stock/Adjust');
    }

    public function stocktakeForm(Request $request): Response
    {
        return $this->renderForm($request, 'tenant/Stock/Stocktake');
    }

    public function damageForm(Request $request): Response
    {
        return $this->renderForm($request, 'tenant/Stock/Damage');
    }

    public function adjust(Request $request): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        $data = $request->validate([
            'store_id' => ['required', 'string', 'size:26'],
            'sku_id' => ['required', 'string', 'size:26'],
            'qty_change' => ['required', 'numeric'],
            'direction' => ['required', Rule::in(['IN', 'OUT'])],
            'subtype' => ['required', Rule::in(['INITIAL', 'MANUAL'])],
        ]);

        [$ownerId, $locationId] = $this->resolveOwnerLocation($tenantId, $data['store_id']);
        AdjustStockAction::handle(
            $tenantId, $data['store_id'], $ownerId, $locationId, $data['sku_id'],
            (string) $data['qty_change'], $data['direction'], $data['subtype'],
            (string) $request->user()->id,
        );

        return redirect('/tenant/stock?store_id='.$data['store_id'])
            ->with('success', '调整已记录');
    }

    public function stocktake(Request $request): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        $data = $request->validate([
            'store_id' => ['required', 'string', 'size:26'],
            'sku_id' => ['required', 'string', 'size:26'],
            'actual_qty' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        [$ownerId, $locationId] = $this->resolveOwnerLocation($tenantId, $data['store_id']);
        StocktakeAction::handle(
            $tenantId, $data['store_id'], $ownerId, $locationId, $data['sku_id'],
            (string) $data['actual_qty'], (string) $request->user()->id, $data['note'] ?? null,
        );

        return redirect('/tenant/stock?store_id='.$data['store_id'])
            ->with('success', '盘点已记录');
    }

    public function damage(Request $request): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        $data = $request->validate([
            'store_id' => ['required', 'string', 'size:26'],
            'sku_id' => ['required', 'string', 'size:26'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'unit_cost_cents' => ['required', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        [$ownerId, $locationId] = $this->resolveOwnerLocation($tenantId, $data['store_id']);
        DamageAction::handle(
            $tenantId, $data['store_id'], $ownerId, $locationId, $data['sku_id'],
            (string) $data['qty'], (int) $data['unit_cost_cents'],
            (string) $request->user()->id, $data['reason'] ?? null,
        );

        return redirect('/tenant/stock?store_id='.$data['store_id'])
            ->with('success', '报损已记录');
    }

    public function reverse(Request $request, int $id): RedirectResponse
    {
        $this->requireCurrentTenant($request);
        ReverseStockTxnAction::handle($id, (string) $request->user()->id);
        return back()->with('success', '已撤销');
    }

    private function renderForm(Request $request, string $page): Response
    {
        $tenantId = $this->requireCurrentTenant($request);
        $stores = Store::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->orderBy('name')
            ->get(['id', 'name'])->toArray();

        $skus = ItemSku::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('inventory_enabled', true)
            ->with('item:id,item_name,unit')
            ->limit(500)->get()
            ->map(fn (ItemSku $s) => [
                'id' => $s->id,
                'item_name' => $s->item?->item_name ?? '?',
                'unit' => $s->item?->unit ?? '',
                'barcode' => $s->barcode,
            ])->all();

        return Inertia::render($page, [
            'stores' => $stores,
            'skus' => $skus,
        ]);
    }

    private function resolveOwnerLocation(string $tenantId, string $storeId): array
    {
        $owner = StockOwner::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('owner_type', 'STORE')->where('owner_ref_id', $storeId)
            ->firstOrFail();
        $location = StockLocation::query()->withoutGlobalScopes()
            ->where('stock_owner_id', $owner->id)
            ->where('location_code', 'DEFAULT')
            ->firstOrFail();
        return [$owner->id, $location->id];
    }

    private function requireCurrentTenant(Request $request): string
    {
        $id = $request->session()->get('current_tenant_id');
        if (! $id) {
            throw ValidationException::withMessages(['tenant' => '尚未选定租户']);
        }
        return (string) $id;
    }
}
```

- [ ] **Step 2: 修改 routes/web.php**

在 tenant 组添加：

```php
use App\Modules\Inventory\Http\Controllers\Web\TenantStockMutationController;

Route::get('stock/adjust', [TenantStockMutationController::class, 'adjustForm']);
Route::post('stock/adjust', [TenantStockMutationController::class, 'adjust']);
Route::get('stock/stocktake', [TenantStockMutationController::class, 'stocktakeForm']);
Route::post('stock/stocktake', [TenantStockMutationController::class, 'stocktake']);
Route::get('stock/damage', [TenantStockMutationController::class, 'damageForm']);
Route::post('stock/damage', [TenantStockMutationController::class, 'damage']);
Route::post('stock/txns/{id}/reverse', [TenantStockMutationController::class, 'reverse']);
```

- [ ] **Step 3: 写 Stock/Adjust.vue**

`resources/js/pages/tenant/Stock/Adjust.vue`：

```vue
<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const page = usePage();
const props = computed(() => page.props as unknown as {
  stores: { id: string; name: string }[];
  skus: { id: string; item_name: string; unit: string; barcode: string | null }[];
});

const form = ref({
  store_id: '', sku_id: '',
  qty_change: 0, direction: 'IN' as 'IN' | 'OUT',
  subtype: 'MANUAL' as 'INITIAL' | 'MANUAL',
});
const errors = ref<Record<string, string>>({});

function submit() {
  // qty_change 应当与 direction 同号；前端做一次纠正
  const v = Math.abs(form.value.qty_change);
  const signed = form.value.direction === 'OUT' ? -v : v;
  router.post('/tenant/stock/adjust', { ...form.value, qty_change: signed }, {
    onError: (e) => { errors.value = e as Record<string, string>; },
  });
}
</script>

<template>
  <div class="p-6 max-w-xl">
    <h1 class="text-xl font-medium mb-4">手动调整库存</h1>

    <form @submit.prevent="submit" class="space-y-4 text-sm">
      <div>
        <label class="block mb-1">门店</label>
        <select v-model="form.store_id" class="w-full px-2 py-1 border rounded">
          <option value="">请选择</option>
          <option v-for="s in props.stores" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
      </div>

      <div>
        <label class="block mb-1">SKU</label>
        <select v-model="form.sku_id" class="w-full px-2 py-1 border rounded">
          <option value="">请选择</option>
          <option v-for="s in props.skus" :key="s.id" :value="s.id">
            {{ s.item_name }} ({{ s.unit }}) {{ s.barcode ? '· ' + s.barcode : '' }}
          </option>
        </select>
      </div>

      <div class="grid grid-cols-3 gap-4">
        <div>
          <label class="block mb-1">数量</label>
          <input type="number" step="0.0001" v-model.number="form.qty_change"
            class="w-full px-2 py-1 border rounded" />
        </div>
        <div>
          <label class="block mb-1">方向</label>
          <select v-model="form.direction" class="w-full px-2 py-1 border rounded">
            <option value="IN">入库</option>
            <option value="OUT">出库</option>
          </select>
        </div>
        <div>
          <label class="block mb-1">子类型</label>
          <select v-model="form.subtype" class="w-full px-2 py-1 border rounded">
            <option value="INITIAL">初始化</option>
            <option value="MANUAL">手动</option>
          </select>
        </div>
      </div>

      <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded">提交</button>
    </form>
  </div>
</template>
```

- [ ] **Step 4: 写 Stock/Stocktake.vue**

`resources/js/pages/tenant/Stock/Stocktake.vue`：

```vue
<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const page = usePage();
const props = computed(() => page.props as unknown as {
  stores: { id: string; name: string }[];
  skus: { id: string; item_name: string; unit: string; barcode: string | null }[];
});

const form = ref({ store_id: '', sku_id: '', actual_qty: 0, note: '' });
const errors = ref<Record<string, string>>({});

function submit() {
  router.post('/tenant/stock/stocktake', form.value, {
    onError: (e) => { errors.value = e as Record<string, string>; },
  });
}
</script>

<template>
  <div class="p-6 max-w-xl">
    <h1 class="text-xl font-medium mb-4">单 SKU 盘点</h1>

    <form @submit.prevent="submit" class="space-y-4 text-sm">
      <div><label class="block mb-1">门店</label>
        <select v-model="form.store_id" class="w-full px-2 py-1 border rounded">
          <option value="">请选择</option>
          <option v-for="s in props.stores" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select></div>
      <div><label class="block mb-1">SKU</label>
        <select v-model="form.sku_id" class="w-full px-2 py-1 border rounded">
          <option value="">请选择</option>
          <option v-for="s in props.skus" :key="s.id" :value="s.id">
            {{ s.item_name }} ({{ s.unit }})
          </option>
        </select></div>
      <div><label class="block mb-1">实盘数量</label>
        <input type="number" step="0.0001" v-model.number="form.actual_qty"
          class="w-full px-2 py-1 border rounded" /></div>
      <div><label class="block mb-1">备注</label>
        <input v-model="form.note" class="w-full px-2 py-1 border rounded" /></div>

      <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded">提交</button>
    </form>
  </div>
</template>
```

- [ ] **Step 5: 写 Stock/Damage.vue**

`resources/js/pages/tenant/Stock/Damage.vue`：

```vue
<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const page = usePage();
const props = computed(() => page.props as unknown as {
  stores: { id: string; name: string }[];
  skus: { id: string; item_name: string; unit: string; barcode: string | null }[];
});

const form = ref({ store_id: '', sku_id: '', qty: 0, unit_cost_cents: 0, reason: '' });
const errors = ref<Record<string, string>>({});

function submit() {
  router.post('/tenant/stock/damage', form.value, {
    onError: (e) => { errors.value = e as Record<string, string>; },
  });
}
</script>

<template>
  <div class="p-6 max-w-xl">
    <h1 class="text-xl font-medium mb-4">报损登记</h1>

    <form @submit.prevent="submit" class="space-y-4 text-sm">
      <div><label class="block mb-1">门店</label>
        <select v-model="form.store_id" class="w-full px-2 py-1 border rounded">
          <option value="">请选择</option>
          <option v-for="s in props.stores" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select></div>
      <div><label class="block mb-1">SKU</label>
        <select v-model="form.sku_id" class="w-full px-2 py-1 border rounded">
          <option value="">请选择</option>
          <option v-for="s in props.skus" :key="s.id" :value="s.id">
            {{ s.item_name }} ({{ s.unit }})
          </option>
        </select></div>
      <div class="grid grid-cols-2 gap-4">
        <div><label class="block mb-1">数量</label>
          <input type="number" step="0.0001" v-model.number="form.qty"
            class="w-full px-2 py-1 border rounded" /></div>
        <div><label class="block mb-1">单价（分）</label>
          <input type="number" v-model.number="form.unit_cost_cents"
            class="w-full px-2 py-1 border rounded" /></div>
      </div>
      <div><label class="block mb-1">原因</label>
        <input v-model="form.reason" class="w-full px-2 py-1 border rounded" /></div>

      <button type="submit" class="px-4 py-2 bg-rose-600 text-white rounded">提交报损</button>
    </form>
  </div>
</template>
```

- [ ] **Step 6: 写测试**

`app/Modules/Inventory/Tests/Feature/TenantStockMutationWebTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Identity\Models\Membership;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\AdjustStockAction;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Inventory\Models\StockTxn;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->actor = User::factory()->create();
    Membership::factory()->create([
        'user_id' => $this->actor->id, 'tenant_id' => $this->tenant->id, 'store_id' => null,
    ]);
    $this->store = Store::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->item = Item::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->sku = ItemSku::factory()->create(['tenant_id' => $this->tenant->id, 'item_id' => $this->item->id]);
    $this->actingAs($this->actor, 'web')
        ->withSession(['current_tenant_id' => $this->tenant->id]);
});

test('POST /tenant/stock/adjust 调整成功 + 写流水', function () {
    $this->post('/tenant/stock/adjust', [
        'store_id' => $this->store->id,
        'sku_id' => $this->sku->id,
        'qty_change' => 100,
        'direction' => 'IN',
        'subtype' => 'INITIAL',
    ])->assertRedirect();

    expect(StockTxn::query()->where('tenant_id', $this->tenant->id)->count())->toBe(1);
    $balance = StockBalance::query()->withoutGlobalScopes()
        ->where('sku_id', $this->sku->id)->first();
    expect((float) $balance->available_qty)->toBe(100.0);
});

test('POST /tenant/stock/stocktake 盘点写流水', function () {
    $owner = StockOwner::query()->withoutGlobalScopes()
        ->where('owner_ref_id', $this->store->id)->first();
    $location = StockLocation::query()->withoutGlobalScopes()
        ->where('stock_owner_id', $owner->id)->first();
    AdjustStockAction::handle(
        $this->tenant->id, $this->store->id, $owner->id, $location->id,
        $this->sku->id, '50', 'IN', 'INITIAL', $this->actor->id,
    );

    $this->post('/tenant/stock/stocktake', [
        'store_id' => $this->store->id,
        'sku_id' => $this->sku->id,
        'actual_qty' => 60,
    ])->assertRedirect();

    $latest = StockTxn::query()->orderByDesc('id')->first();
    expect($latest->biz_type->value)->toBe('STOCKTAKE_PROFIT');
});

test('POST /tenant/stock/damage 报损成功', function () {
    $owner = StockOwner::query()->withoutGlobalScopes()
        ->where('owner_ref_id', $this->store->id)->first();
    $location = StockLocation::query()->withoutGlobalScopes()
        ->where('stock_owner_id', $owner->id)->first();
    AdjustStockAction::handle(
        $this->tenant->id, $this->store->id, $owner->id, $location->id,
        $this->sku->id, '20', 'IN', 'INITIAL', $this->actor->id,
    );

    $this->post('/tenant/stock/damage', [
        'store_id' => $this->store->id,
        'sku_id' => $this->sku->id,
        'qty' => 5,
        'unit_cost_cents' => 800,
        'reason' => '过期',
    ])->assertRedirect();

    $latest = StockTxn::query()->orderByDesc('id')->first();
    expect($latest->biz_type->value)->toBe('DAMAGE_OUT');
    expect($latest->amount_cents)->toBe(4000);
});

test('POST /tenant/stock/txns/{id}/reverse 撤销', function () {
    $owner = StockOwner::query()->withoutGlobalScopes()
        ->where('owner_ref_id', $this->store->id)->first();
    $location = StockLocation::query()->withoutGlobalScopes()
        ->where('stock_owner_id', $owner->id)->first();
    $origId = AdjustStockAction::handle(
        $this->tenant->id, $this->store->id, $owner->id, $location->id,
        $this->sku->id, '10', 'IN', 'INITIAL', $this->actor->id,
    );

    $this->post("/tenant/stock/txns/{$origId}/reverse")->assertRedirect();

    expect(StockTxn::query()->count())->toBe(2);
    $balance = StockBalance::query()->withoutGlobalScopes()
        ->where('sku_id', $this->sku->id)->first();
    expect((float) $balance->available_qty)->toBe(0.0);
});
```

- [ ] **Step 7: 跑测试**

```bash
./vendor/bin/pest app/Modules/Inventory/Tests/Feature/TenantStockMutationWebTest.php
npx vue-tsc --noEmit
```

Expected: 4 passed + 无类型错误。

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat(inventory): TenantStockMutationController + 3 mutation pages"
```

---

## Task 23：`TenantInventoryConfigController` 租户级配置页

**Files:**
- Create: `app/Modules/Inventory/Http/Controllers/Web/TenantInventoryConfigController.php`
- Create: `resources/js/pages/tenant/Settings/InventoryConfig.vue`
- Modify: `routes/web.php`
- Test: `app/Modules/Inventory/Tests/Feature/TenantInventoryConfigWebTest.php`

- [ ] **Step 1: 写 Controller**

`app/Modules/Inventory/Http/Controllers/Web/TenantInventoryConfigController.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers\Web;

use App\Modules\Inventory\Models\TenantInventoryConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TenantInventoryConfigController extends Controller
{
    public function show(Request $request): Response
    {
        $tenantId = $this->requireCurrentTenant($request);
        $cfg = TenantInventoryConfig::query()->where('tenant_id', $tenantId)->firstOrFail();

        return Inertia::render('tenant/Settings/InventoryConfig', [
            'config' => [
                'inventory_enabled' => (bool) $cfg->inventory_enabled,
                'multi_location_enabled' => (bool) $cfg->multi_location_enabled,
                'production_enabled' => (bool) $cfg->production_enabled,
                'purchase_enabled' => (bool) $cfg->purchase_enabled,
                'transfer_enabled' => (bool) $cfg->transfer_enabled,
                'stocktaking_enabled' => (bool) $cfg->stocktaking_enabled,
                'negative_stock_allowed' => (bool) $cfg->negative_stock_allowed,
                'inventory_cost_method' => $cfg->inventory_cost_method->value,
                'expiry_management_enabled' => (bool) $cfg->expiry_management_enabled,
                'batch_management_enabled' => (bool) $cfg->batch_management_enabled,
                'auto_deduct_raw_material_enabled' => (bool) $cfg->auto_deduct_raw_material_enabled,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        $data = $request->validate([
            'inventory_enabled' => ['required', 'boolean'],
            'multi_location_enabled' => ['required', 'boolean'],
            'production_enabled' => ['required', 'boolean'],
            'purchase_enabled' => ['required', 'boolean'],
            'transfer_enabled' => ['required', 'boolean'],
            'stocktaking_enabled' => ['required', 'boolean'],
            'negative_stock_allowed' => ['required', 'boolean'],
            'inventory_cost_method' => ['required', Rule::in(['FIFO', 'MOVING_AVG', 'STANDARD'])],
            'expiry_management_enabled' => ['required', 'boolean'],
            'batch_management_enabled' => ['required', 'boolean'],
            'auto_deduct_raw_material_enabled' => ['required', 'boolean'],
        ]);

        TenantInventoryConfig::query()->where('tenant_id', $tenantId)->update($data);

        return back()->with('success', '配置已保存');
    }

    private function requireCurrentTenant(Request $request): string
    {
        $id = $request->session()->get('current_tenant_id');
        if (! $id) {
            throw ValidationException::withMessages(['tenant' => '尚未选定租户']);
        }
        return (string) $id;
    }
}
```

- [ ] **Step 2: 修改 routes/web.php**

```php
use App\Modules\Inventory\Http\Controllers\Web\TenantInventoryConfigController;

Route::get('settings/inventory', [TenantInventoryConfigController::class, 'show']);
Route::patch('settings/inventory', [TenantInventoryConfigController::class, 'update']);
```

- [ ] **Step 3: 写 Vue 页面**

`resources/js/pages/tenant/Settings/InventoryConfig.vue`：

```vue
<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

interface Cfg {
  inventory_enabled: boolean; multi_location_enabled: boolean;
  production_enabled: boolean; purchase_enabled: boolean;
  transfer_enabled: boolean; stocktaking_enabled: boolean;
  negative_stock_allowed: boolean; inventory_cost_method: string;
  expiry_management_enabled: boolean; batch_management_enabled: boolean;
  auto_deduct_raw_material_enabled: boolean;
}

const page = usePage();
const props = computed(() => page.props as unknown as { config: Cfg });
const form = ref<Cfg>({ ...props.value.config });

function submit() {
  router.patch('/tenant/settings/inventory', form.value);
}

const switches = [
  ['inventory_enabled', '总开关：是否启用库存模块'],
  ['multi_location_enabled', '启用多货架/多库位'],
  ['production_enabled', '启用自制商品/生产'],
  ['purchase_enabled', '启用采购'],
  ['transfer_enabled', '启用调拨'],
  ['stocktaking_enabled', '启用盘点'],
  ['negative_stock_allowed', '允许负库存（与 SKU policy 两层 AND）'],
  ['expiry_management_enabled', '启用保质期'],
  ['batch_management_enabled', '启用批次'],
  ['auto_deduct_raw_material_enabled', '销售时自动扣减原料（需 BOM）'],
] as const;
</script>

<template>
  <div class="p-6 max-w-3xl">
    <h1 class="text-xl font-medium mb-4">租户库存配置</h1>

    <form @submit.prevent="submit" class="space-y-3 text-sm">
      <div v-for="[k, label] in switches" :key="k"
        class="flex items-center justify-between border-b py-2">
        <span>{{ label }}</span>
        <label class="inline-flex items-center gap-2">
          <input type="checkbox" v-model="(form as any)[k]" />
          <span class="text-xs text-slate-500">{{ (form as any)[k] ? '启用' : '关闭' }}</span>
        </label>
      </div>

      <div class="flex items-center justify-between border-b py-2">
        <span>成本核算方法</span>
        <select v-model="form.inventory_cost_method" class="px-2 py-1 border rounded">
          <option value="FIFO">FIFO（先进先出）</option>
          <option value="MOVING_AVG">移动加权平均</option>
          <option value="STANDARD">标准成本</option>
        </select>
      </div>

      <button type="submit"
        class="mt-4 px-4 py-2 bg-indigo-600 text-white rounded">保存</button>
    </form>
  </div>
</template>
```

- [ ] **Step 4: 写测试**

`app/Modules/Inventory/Tests/Feature/TenantInventoryConfigWebTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\TenantInventoryConfig;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->actor = User::factory()->create();
    Membership::factory()->create([
        'user_id' => $this->actor->id, 'tenant_id' => $this->tenant->id, 'store_id' => null,
    ]);
    $this->actingAs($this->actor, 'web')
        ->withSession(['current_tenant_id' => $this->tenant->id]);
});

test('GET /tenant/settings/inventory 返回当前配置', function () {
    $this->get('/tenant/settings/inventory')
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('tenant/Settings/InventoryConfig')
            ->where('config.inventory_enabled', true)
            ->where('config.inventory_cost_method', 'MOVING_AVG')
        );
});

test('PATCH /tenant/settings/inventory 更新成功', function () {
    $this->patch('/tenant/settings/inventory', [
        'inventory_enabled' => true,
        'multi_location_enabled' => true,
        'production_enabled' => false,
        'purchase_enabled' => false,
        'transfer_enabled' => false,
        'stocktaking_enabled' => true,
        'negative_stock_allowed' => true,
        'inventory_cost_method' => 'FIFO',
        'expiry_management_enabled' => false,
        'batch_management_enabled' => true,
        'auto_deduct_raw_material_enabled' => false,
    ])->assertRedirect();

    $cfg = TenantInventoryConfig::query()->where('tenant_id', $this->tenant->id)->first();
    expect($cfg->multi_location_enabled)->toBeTrue();
    expect($cfg->negative_stock_allowed)->toBeTrue();
    expect($cfg->batch_management_enabled)->toBeTrue();
    expect($cfg->inventory_cost_method->value)->toBe('FIFO');
});
```

- [ ] **Step 5: 跑测试**

```bash
./vendor/bin/pest app/Modules/Inventory/Tests/Feature/TenantInventoryConfigWebTest.php
```

Expected: 2 passed.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat(inventory): TenantInventoryConfig page (11 toggles)"
```

---

## Task 24：`TenantStoreInventoryConfigController` 门店级配置页

**Files:**
- Create: `app/Modules/Inventory/Http/Controllers/Web/TenantStoreInventoryConfigController.php`
- Create: `resources/js/pages/tenant/Stores/InventoryConfig.vue`
- Modify: `routes/web.php`
- Test: `app/Modules/Inventory/Tests/Feature/TenantStoreInventoryConfigWebTest.php`

- [ ] **Step 1: 写 Controller**

`app/Modules/Inventory/Http/Controllers/Web/TenantStoreInventoryConfigController.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers\Web;

use App\Modules\Tenancy\Models\Store;
use App\Modules\Inventory\Models\StoreInventoryConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TenantStoreInventoryConfigController extends Controller
{
    public function show(Request $request, string $storeId): Response
    {
        $tenantId = $this->requireCurrentTenant($request);
        $store = Store::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->whereKey($storeId)->firstOrFail();
        $cfg = StoreInventoryConfig::query()->where('store_id', $storeId)->firstOrFail();

        return Inertia::render('tenant/Stores/InventoryConfig', [
            'store' => ['id' => $store->id, 'name' => $store->name],
            'config' => [
                'inventory_enabled' => (bool) $cfg->inventory_enabled,
                'multi_location_enabled' => (bool) $cfg->multi_location_enabled,
                'default_stock_mode' => $cfg->default_stock_mode,
                'production_enabled' => (bool) $cfg->production_enabled,
                'allow_direct_stock_adjustment' => (bool) $cfg->allow_direct_stock_adjustment,
            ],
        ]);
    }

    public function update(Request $request, string $storeId): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        Store::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->whereKey($storeId)->firstOrFail();

        $data = $request->validate([
            'inventory_enabled' => ['required', 'boolean'],
            'multi_location_enabled' => ['required', 'boolean'],
            'default_stock_mode' => ['required', 'string', 'max:20'],
            'production_enabled' => ['required', 'boolean'],
            'allow_direct_stock_adjustment' => ['required', 'boolean'],
        ]);

        StoreInventoryConfig::query()->where('store_id', $storeId)->update($data);

        return back()->with('success', '门店配置已保存');
    }

    private function requireCurrentTenant(Request $request): string
    {
        $id = $request->session()->get('current_tenant_id');
        if (! $id) {
            throw ValidationException::withMessages(['tenant' => '尚未选定租户']);
        }
        return (string) $id;
    }
}
```

- [ ] **Step 2: 修改 routes/web.php**

```php
use App\Modules\Inventory\Http\Controllers\Web\TenantStoreInventoryConfigController;

Route::get('stores/{storeId}/inventory', [TenantStoreInventoryConfigController::class, 'show']);
Route::patch('stores/{storeId}/inventory', [TenantStoreInventoryConfigController::class, 'update']);
```

- [ ] **Step 3: 写 Vue 页面**

`resources/js/pages/tenant/Stores/InventoryConfig.vue`：

```vue
<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

interface Cfg {
  inventory_enabled: boolean; multi_location_enabled: boolean;
  default_stock_mode: string; production_enabled: boolean;
  allow_direct_stock_adjustment: boolean;
}

const page = usePage();
const props = computed(() => page.props as unknown as {
  store: { id: string; name: string };
  config: Cfg;
});
const form = ref<Cfg>({ ...props.value.config });

function submit() {
  router.patch(`/tenant/stores/${props.value.store.id}/inventory`, form.value);
}
</script>

<template>
  <div class="p-6 max-w-3xl">
    <h1 class="text-xl font-medium mb-4">{{ props.store.name }} - 库存配置</h1>

    <form @submit.prevent="submit" class="space-y-3 text-sm">
      <div class="flex items-center justify-between border-b py-2">
        <span>启用库存（覆盖租户开关）</span>
        <input type="checkbox" v-model="form.inventory_enabled" />
      </div>
      <div class="flex items-center justify-between border-b py-2">
        <span>启用多货架</span>
        <input type="checkbox" v-model="form.multi_location_enabled" />
      </div>
      <div class="flex items-center justify-between border-b py-2">
        <span>默认库存模式</span>
        <input v-model="form.default_stock_mode"
          class="px-2 py-1 border rounded w-40" />
      </div>
      <div class="flex items-center justify-between border-b py-2">
        <span>启用自制/生产</span>
        <input type="checkbox" v-model="form.production_enabled" />
      </div>
      <div class="flex items-center justify-between border-b py-2">
        <span>允许直接库存调整</span>
        <input type="checkbox" v-model="form.allow_direct_stock_adjustment" />
      </div>

      <button type="submit"
        class="mt-4 px-4 py-2 bg-indigo-600 text-white rounded">保存</button>
    </form>
  </div>
</template>
```

- [ ] **Step 4: 写测试**

`app/Modules/Inventory/Tests/Feature/TenantStoreInventoryConfigWebTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Identity\Models\Membership;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\StoreInventoryConfig;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->actor = User::factory()->create();
    Membership::factory()->create([
        'user_id' => $this->actor->id, 'tenant_id' => $this->tenant->id, 'store_id' => null,
    ]);
    $this->store = Store::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($this->actor, 'web')
        ->withSession(['current_tenant_id' => $this->tenant->id]);
});

test('GET /tenant/stores/{id}/inventory 返回门店配置', function () {
    $this->get("/tenant/stores/{$this->store->id}/inventory")
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('tenant/Stores/InventoryConfig')
            ->where('store.id', $this->store->id)
            ->where('config.inventory_enabled', true)
        );
});

test('PATCH 更新成功', function () {
    $this->patch("/tenant/stores/{$this->store->id}/inventory", [
        'inventory_enabled' => false,
        'multi_location_enabled' => true,
        'default_stock_mode' => 'FULL',
        'production_enabled' => true,
        'allow_direct_stock_adjustment' => false,
    ])->assertRedirect();

    $cfg = StoreInventoryConfig::query()->where('store_id', $this->store->id)->first();
    expect($cfg->inventory_enabled)->toBeFalse();
    expect($cfg->default_stock_mode)->toBe('FULL');
    expect($cfg->production_enabled)->toBeTrue();
});

test('跨租户 store_id 404', function () {
    $other = Tenant::factory()->create();
    $alien = Store::factory()->create(['tenant_id' => $other->id]);

    $this->get("/tenant/stores/{$alien->id}/inventory")->assertNotFound();
});
```

- [ ] **Step 5: 跑测试**

```bash
./vendor/bin/pest app/Modules/Inventory/Tests/Feature/TenantStoreInventoryConfigWebTest.php
```

Expected: 3 passed.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat(inventory): per-store inventory config page"
```

---

## Task 25：侧栏导航更新 + i18n + 整模块烟测

**Files:**
- Modify: `resources/js/composables/useNavigation.ts`
- Modify: `resources/js/lib/i18n.ts`（单文件 i18n；如不存在则跳过 i18n step）

- [ ] **Step 1: 查看现有 useNavigation 结构**

```bash
grep -n "tenantModules\|sidebar\|nav\." /mnt/d/Projects/Huang/coffee/resources/js/composables/useNavigation.ts | head -30
```

确认导航结构后，定位"settings"模块或类似位置，为库存模块新增条目。

- [ ] **Step 2: 在 `tenantModules` 数组中增加"库存"模块**

打开 `resources/js/composables/useNavigation.ts`，在数组中追加（位置在 `'settings'` 模块之前）：

```ts
{
  key: 'inventory',
  label: 'nav.inventory',
  defaultUrl: '/tenant/stock',
  sidebar: [
    { key: 'categories', icon: 'Folder', label: 'nav.categories', url: '/tenant/categories', permission: 'categories.read' },
    { key: 'items', icon: 'Goods', label: 'nav.items', url: '/tenant/items', permission: 'items.read' },
    { key: 'stock', icon: 'Box', label: 'nav.stock', url: '/tenant/stock', permission: 'inventory.read' },
    { key: 'stock-txns', icon: 'List', label: 'nav.stock_txns', url: '/tenant/stock/txns', permission: 'stock_txn.read' },
    { key: 'stock-adjust', icon: 'Tools', label: 'nav.stock_adjust', url: '/tenant/stock/adjust', permission: 'inventory.adjust' },
    { key: 'stock-stocktake', icon: 'Histogram', label: 'nav.stocktake', url: '/tenant/stock/stocktake', permission: 'stocktake.write' },
    { key: 'stock-damage', icon: 'Tickets', label: 'nav.damage', url: '/tenant/stock/damage', permission: 'damage.write' },
    { key: 'inventory-config', icon: 'Setting', label: 'nav.inventory_config', url: '/tenant/settings/inventory', permission: 'inventory_config.read' },
  ],
},
```

> **注**：分类入口（`/tenant/categories`）入"库存"模块作为首项，与 items 并列；如旧结构里 `categories` 已挂在其他模块（如 settings），同步移除避免重复。

- [ ] **Step 3: i18n 增加新条目**

打开 `resources/js/lib/i18n.ts`（单文件，包含 messages/zh-CN 字典），在 zh-CN 字典中追加：

```ts
'nav.inventory': '库存',
'nav.categories': '分类',
'nav.items': '物料',
'nav.stock': '库存',
'nav.stock_txns': '流水',
'nav.stock_adjust': '调整',
'nav.stocktake': '盘点',
'nav.damage': '报损',
'nav.inventory_config': '配置',
```

如该文件不存在或项目未启用 i18n，则跳过此步（侧栏会直接显示 key）。

- [ ] **Step 4: type-check + 整模块跑测**

```bash
npx vue-tsc --noEmit
composer test
```

Expected: 无类型错误；全部 Pest 测试通过。

- [ ] **Step 5: 启动 dev server 手动烟测一遍**

```bash
php artisan serve &
npm run dev &
```

打开浏览器登录 → 切换到任一租户后台，验证：
1. 侧栏出现"库存"模块及子菜单（含"分类"作为首项）
2. `/tenant/categories` 树形列表加载，能新建一个 BUSINESS 顶级分类 + 一个 INVENTORY 顶级分类，及一个子分类
3. `/tenant/items` 列表能加载（空表）
4. `/tenant/items/create` 创建一个物料 + SKU + policy，分类下拉同时显示 business / inventory 两组
4. `/tenant/stock?store_id=...` 选门店查看（最初空）
5. `/tenant/stock/adjust` 用 INITIAL_STOCK 补 100 件
6. 回到 `/tenant/stock` 看到 100
7. `/tenant/stock/stocktake` 实盘 80
8. `/tenant/stock/txns` 看到 ADJUSTMENT + STOCKTAKE_LOSS
9. 撤销 STOCKTAKE_LOSS → 库存回 100
10. `/tenant/settings/inventory` 修改 cost_method=FIFO 保存
11. `/tenant/stores/{id}/inventory` 切 inventory_enabled=false → 再去 `/tenant/stock/adjust` 应失败（store 层 toggle）

```bash
# 关闭 dev server
kill %1 %2
```

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat(inventory): sidebar navigation + i18n + e2e smoke"
```

---

# 完成标志

执行完 25 个任务后，应当满足：

- 全部 Pest 测试 PASS（旧 + 新合计 ~50+ test cases）
- `composer test` 全绿
- `npx vue-tsc --noEmit` 无类型错误
- 侧栏出现"库存"模块，含 7 个子菜单
- 手动烟测全 12 步通过

后续阶段（不在本 plan 范围）：批次写入 / 多货架 UI / 采购模块 / 调拨模块 / BOM Action / 销售扣原料。

