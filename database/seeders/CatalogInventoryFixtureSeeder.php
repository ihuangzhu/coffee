<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Identity\Models\Membership;
use App\Modules\Inventory\Models\Bom;
use App\Modules\Inventory\Models\BomComponent;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Inventory\Models\StockQuant;
use App\Modules\Inventory\Models\StockTxn;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 商品 + 库存 dev fixture：
 *   - 业务分类树：饮品(咖啡/茶饮)、甜品
 *   - 库存分类树：原料(咖啡豆/牛奶/糖浆)、包材
 *   - 销售商品 SKU：美式 / 拿铁 / 卡布奇诺 / 抹茶拿铁 / 提拉米苏
 *   - 原料 / 包材 SKU：阿拉比卡咖啡豆 / 全脂牛奶 / 香草糖浆 / 一次性纸杯
 *   - 每个门店：StockOwner + 默认货架 StockLocation + 初始库存 quants
 *   - 每个门店：3 ~ 6 条 StockTxn（PURCHASE_IN / PRODUCTION_CONSUME / DAMAGE_OUT）
 *   - 每租户：3 个 BOM（拿铁 / 卡布奇诺 / 美式）
 *
 * 全程通过 CurrentTenant 注入 tenant_id；写入用 firstOrCreate / 已存在判断，重跑安全。
 * 依赖 IdentitySkeletonSeeder 已创建的两个租户、门店、用户。
 */
class CatalogInventoryFixtureSeeder extends Seeder
{
    public function run(): void
    {
        // store_name => qty_multiplier（库存倍率，便于体现门店间差异）
        $tenants = [
            '九号咖啡' => ['上海徐汇店' => 1.0, '北京朝阳店' => 0.6],
            '示范咖啡' => ['演示门店' => 0.4],
        ];

        DB::transaction(function () use ($tenants) {
            foreach ($tenants as $tenantName => $stores) {
                $tenant = Tenant::query()->where('name', $tenantName)->first();
                if (! $tenant) {
                    $this->command?->warn("跳过：租户 {$tenantName} 不存在，请先跑 IdentitySkeletonSeeder");
                    continue;
                }

                /** @var CurrentTenant $ctx */
                $ctx = app(CurrentTenant::class);
                $ctx->set($tenant->id);

                try {
                    [, $bizCoffee, $bizTea, $bizDessert] = $this->seedBusinessCategories();
                    [, $invBeans, $invMilk, $invSyrup, $invPack] = $this->seedInventoryCategories();

                    $skus = $this->seedItems(
                        $bizCoffee, $bizTea, $bizDessert,
                        $invBeans, $invMilk, $invSyrup, $invPack,
                    );

                    $this->seedBoms($skus);

                    $operator = $this->resolveOperator($tenant->id);

                    foreach ($stores as $storeName => $multiplier) {
                        $store = Store::query()->withoutGlobalScopes()
                            ->where('tenant_id', $tenant->id)
                            ->where('name', $storeName)->first();
                        if (! $store) {
                            continue;
                        }

                        $owner = $this->seedStockOwner($store);
                        $location = $this->seedDefaultLocation($owner);
                        $this->seedInitialStock($owner, $location, $skus, $multiplier);
                        $this->seedStockTxns($owner, $location, $skus, $operator);
                    }
                } finally {
                    $ctx->set(null);
                }
            }
        });

        $this->command?->info('=== 商品 / 库存 fixture 已就绪 ===');
        $this->command?->info('每租户：9 分类 / 9 SKU / 3 BOM');
        $this->command?->info('每门店：1 仓 + 默认货架 + 4 条 quant + 数条 txn');
    }

    /** @return array{0:Category,1:Category,2:Category,3:Category} 根/咖啡/茶饮/甜品 */
    private function seedBusinessCategories(): array
    {
        $root = $this->upsertCategory(null, [
            'category_type' => 'BUSINESS', 'item_type_scope' => 'SALE_PRODUCT',
            'name' => '饮品', 'code' => 'B-DRINK', 'sort_no' => 10,
        ]);
        $coffee = $this->upsertCategory($root, [
            'category_type' => 'BUSINESS', 'item_type_scope' => 'SALE_PRODUCT',
            'name' => '咖啡', 'code' => 'B-COFFEE', 'sort_no' => 10,
        ]);
        $tea = $this->upsertCategory($root, [
            'category_type' => 'BUSINESS', 'item_type_scope' => 'SALE_PRODUCT',
            'name' => '茶饮', 'code' => 'B-TEA', 'sort_no' => 20,
        ]);
        $dessert = $this->upsertCategory(null, [
            'category_type' => 'BUSINESS', 'item_type_scope' => 'SALE_PRODUCT',
            'name' => '甜品', 'code' => 'B-DESSERT', 'sort_no' => 20,
        ]);

        return [$root, $coffee, $tea, $dessert];
    }

    /** @return array{0:Category,1:Category,2:Category,3:Category,4:Category} 原料/咖啡豆/牛奶/糖浆/包材 */
    private function seedInventoryCategories(): array
    {
        $root = $this->upsertCategory(null, [
            'category_type' => 'INVENTORY', 'item_type_scope' => 'RAW_MATERIAL',
            'name' => '原料', 'code' => 'I-RAW', 'sort_no' => 10,
        ]);
        $beans = $this->upsertCategory($root, [
            'category_type' => 'INVENTORY', 'item_type_scope' => 'RAW_MATERIAL',
            'name' => '咖啡豆', 'code' => 'I-BEAN', 'sort_no' => 10,
        ]);
        $milk = $this->upsertCategory($root, [
            'category_type' => 'INVENTORY', 'item_type_scope' => 'RAW_MATERIAL',
            'name' => '牛奶', 'code' => 'I-MILK', 'sort_no' => 20,
        ]);
        $syrup = $this->upsertCategory($root, [
            'category_type' => 'INVENTORY', 'item_type_scope' => 'RAW_MATERIAL',
            'name' => '糖浆', 'code' => 'I-SYRUP', 'sort_no' => 30,
        ]);
        $pack = $this->upsertCategory(null, [
            'category_type' => 'INVENTORY', 'item_type_scope' => 'PACKAGE',
            'name' => '包材', 'code' => 'I-PACK', 'sort_no' => 20,
        ]);

        return [$root, $beans, $milk, $syrup, $pack];
    }

    /** @return array<string,ItemSku> code => sku */
    private function seedItems(
        Category $bizCoffee, Category $bizTea, Category $bizDessert,
        Category $invBeans, Category $invMilk, Category $invSyrup, Category $invPack,
    ): array {
        $skus = [];

        $skus['AMERICANO'] = $this->upsertSaleItem($bizCoffee, '美式咖啡', 'PCS', 'SKU-AM-001', 1800, 400);
        $skus['LATTE'] = $this->upsertSaleItem($bizCoffee, '拿铁', 'PCS', 'SKU-LA-001', 2500, 700);
        $skus['CAPPUCCINO'] = $this->upsertSaleItem($bizCoffee, '卡布奇诺', 'PCS', 'SKU-CA-001', 2500, 700);
        $skus['MATCHA_LATTE'] = $this->upsertSaleItem($bizTea, '抹茶拿铁', 'PCS', 'SKU-MT-001', 2800, 900);
        $skus['TIRAMISU'] = $this->upsertSaleItem($bizDessert, '提拉米苏', 'PCS', 'SKU-TM-001', 3800, 1500);

        $skus['BEAN_ARABICA'] = $this->upsertRawItem($invBeans, '阿拉比卡咖啡豆', 'G', 'SKU-BEAN-AR', 0, 8);
        $skus['MILK_WHOLE'] = $this->upsertRawItem($invMilk, '全脂牛奶', 'ML', 'SKU-MILK-W', 0, 1);
        $skus['SYRUP_VANILLA'] = $this->upsertRawItem($invSyrup, '香草糖浆', 'ML', 'SKU-SYP-V', 0, 5);

        $skus['CUP_PAPER_12OZ'] = $this->upsertPackageItem($invPack, '一次性纸杯 12oz', 'PCS', 'SKU-CUP-12', 0, 30);

        return $skus;
    }

    /** @param array<string,ItemSku> $skus */
    private function seedBoms(array $skus): void
    {
        // 标准配方：output_qty = 1，components 用克 / 毫升 / 个
        $recipes = [
            'LATTE' => [
                ['BEAN_ARABICA', 18, 0.0],
                ['MILK_WHOLE', 200, 0.05],
                ['CUP_PAPER_12OZ', 1, 0.0],
            ],
            'CAPPUCCINO' => [
                ['BEAN_ARABICA', 18, 0.0],
                ['MILK_WHOLE', 150, 0.05],
                ['CUP_PAPER_12OZ', 1, 0.0],
            ],
            'AMERICANO' => [
                ['BEAN_ARABICA', 18, 0.0],
                ['CUP_PAPER_12OZ', 1, 0.0],
            ],
        ];

        foreach ($recipes as $outputCode => $components) {
            $output = $skus[$outputCode] ?? null;
            if (! $output) {
                continue;
            }

            $bom = Bom::query()
                ->where('output_sku_id', $output->id)
                ->where('bom_type', 'STANDARD')
                ->whereNull('store_id')
                ->first();

            if (! $bom) {
                $bom = Bom::query()->create([
                    'output_sku_id' => $output->id,
                    'output_qty' => 1,
                    'bom_type' => 'STANDARD',
                    'store_id' => null,
                    'status' => 'active',
                ]);
            }

            // components 已存在则跳过（按 component_sku_id 去重）
            foreach ($components as $i => [$code, $qty, $loss]) {
                $component = $skus[$code] ?? null;
                if (! $component) {
                    continue;
                }
                $exists = BomComponent::query()
                    ->where('bom_id', $bom->id)
                    ->where('component_sku_id', $component->id)
                    ->exists();
                if ($exists) {
                    continue;
                }
                BomComponent::query()->create([
                    'bom_id' => $bom->id,
                    'component_sku_id' => $component->id,
                    'consume_qty' => $qty,
                    'loss_rate' => $loss,
                    'sequence_no' => $i + 1,
                ]);
            }
        }
    }

    private function upsertCategory(?Category $parent, array $attrs): Category
    {
        $cat = Category::query()
            ->where('owner_type', 'TENANT')
            ->where('name', $attrs['name'])
            ->where(function ($q) use ($parent) {
                $parent ? $q->where('parent_id', $parent->id) : $q->whereNull('parent_id');
            })
            ->first();
        if ($cat) {
            return $cat;
        }
        $compute = (new Category)->computePathAndLevel($parent);

        return Category::query()->create(array_merge([
            'owner_type' => 'TENANT',
            'owner_store_id' => null,
            'parent_id' => $parent?->id,
            'level' => $compute['level'],
            'path' => $compute['path'],
            'status' => 'active',
        ], $attrs));
    }

    private function upsertSaleItem(Category $biz, string $name, string $unit, string $barcode, int $sale, int $cost): ItemSku
    {
        return $this->upsertItemWithSku([
            'item_type' => 'SALE_PRODUCT', 'item_name' => $name, 'unit' => $unit,
            'business_category_id' => $biz->id, 'inventory_category_id' => null,
            'inventory_enabled' => true,
        ], ['barcode' => $barcode, 'sale_price_cents' => $sale, 'cost_price_cents' => $cost]);
    }

    private function upsertRawItem(Category $inv, string $name, string $unit, string $barcode, int $sale, int $cost): ItemSku
    {
        return $this->upsertItemWithSku([
            'item_type' => 'RAW_MATERIAL', 'item_name' => $name, 'unit' => $unit,
            'business_category_id' => null, 'inventory_category_id' => $inv->id,
            'inventory_enabled' => true,
        ], ['barcode' => $barcode, 'sale_price_cents' => $sale, 'cost_price_cents' => $cost]);
    }

    private function upsertPackageItem(Category $inv, string $name, string $unit, string $barcode, int $sale, int $cost): ItemSku
    {
        return $this->upsertItemWithSku([
            'item_type' => 'PACKAGE', 'item_name' => $name, 'unit' => $unit,
            'business_category_id' => null, 'inventory_category_id' => $inv->id,
            'inventory_enabled' => true,
        ], ['barcode' => $barcode, 'sale_price_cents' => $sale, 'cost_price_cents' => $cost]);
    }

    private function upsertItemWithSku(array $itemAttrs, array $skuAttrs): ItemSku
    {
        $item = Item::query()
            ->where('item_name', $itemAttrs['item_name'])
            ->where('item_type', $itemAttrs['item_type'])
            ->first();
        if (! $item) {
            $item = Item::query()->create(array_merge([
                'owner_type' => 'TENANT', 'owner_store_id' => null,
                'sku_enabled' => true, 'status' => 'active',
            ], $itemAttrs));
        }

        $sku = ItemSku::query()->where('item_id', $item->id)->first();
        if (! $sku) {
            // ItemSkuObserver 会自动建 ProductInventoryPolicy
            $sku = ItemSku::query()->create(array_merge([
                'item_id' => $item->id, 'spec_json' => null, 'inventory_enabled' => true,
            ], $skuAttrs));
        }

        return $sku;
    }

    private function seedStockOwner(Store $store): StockOwner
    {
        return StockOwner::query()->firstOrCreate(
            ['tenant_id' => $store->tenant_id, 'owner_type' => 'STORE', 'owner_ref_id' => $store->id],
            ['name' => $store->name.' 主仓', 'status' => 'active'],
        );
    }

    private function seedDefaultLocation(StockOwner $owner): StockLocation
    {
        return StockLocation::query()->firstOrCreate(
            ['stock_owner_id' => $owner->id, 'location_code' => 'MAIN'],
            [
                'tenant_id' => $owner->tenant_id, 'location_name' => '默认货架',
                'location_type' => 'SHELF', 'status' => 'active',
            ],
        );
    }

    /** @param array<string,ItemSku> $skus */
    private function seedInitialStock(StockOwner $owner, StockLocation $location, array $skus, float $mul): void
    {
        $base = [
            'BEAN_ARABICA' => ['qty' => 5000, 'cost' => 8],
            'MILK_WHOLE' => ['qty' => 12000, 'cost' => 1],
            'SYRUP_VANILLA' => ['qty' => 2000, 'cost' => 5],
            'CUP_PAPER_12OZ' => ['qty' => 500, 'cost' => 30],
        ];
        foreach ($base as $code => $cfg) {
            if (! isset($skus[$code])) {
                continue;
            }
            $existing = StockQuant::query()
                ->where('stock_owner_id', $owner->id)
                ->where('location_id', $location->id)
                ->where('sku_id', $skus[$code]->id)
                ->whereNull('batch_no')
                ->first();
            if ($existing) {
                continue;
            }
            StockQuant::query()->create([
                'tenant_id' => $owner->tenant_id,
                'stock_owner_id' => $owner->id,
                'location_id' => $location->id,
                'sku_id' => $skus[$code]->id,
                'batch_no' => null,
                'expiry_date' => null,
                'unit_cost_cents' => $cfg['cost'],
                'qty' => (int) round($cfg['qty'] * $mul),
            ]);
        }
    }

    /** @param array<string,ItemSku> $skus */
    private function seedStockTxns(StockOwner $owner, StockLocation $location, array $skus, string $operator): void
    {
        // 已有任何流水则跳过（避免重跑放大）
        $hasTxn = StockTxn::query()
            ->where('stock_owner_id', $owner->id)->exists();
        if ($hasTxn) {
            return;
        }

        $now = Carbon::now();
        $entries = [
            // 7 天前进货：4 条 PURCHASE_IN
            ['BEAN_ARABICA',   'PURCHASE_IN',        'IN',  5000, 8,  $now->copy()->subDays(7)],
            ['MILK_WHOLE',     'PURCHASE_IN',        'IN',  12000, 1, $now->copy()->subDays(7)],
            ['SYRUP_VANILLA',  'PURCHASE_IN',        'IN',  2000, 5,  $now->copy()->subDays(7)],
            ['CUP_PAPER_12OZ', 'PURCHASE_IN',        'IN',  500, 30,  $now->copy()->subDays(7)],
            // 2 天前生产消耗
            ['BEAN_ARABICA',   'PRODUCTION_CONSUME', 'OUT', -360, 8,  $now->copy()->subDays(2)],
            ['MILK_WHOLE',     'PRODUCTION_CONSUME', 'OUT', -2000, 1, $now->copy()->subDays(2)],
            // 昨天报损
            ['MILK_WHOLE',     'DAMAGE_OUT',         'OUT', -500, 1,  $now->copy()->subDay()],
        ];

        foreach ($entries as [$code, $bizType, $direction, $qty, $cost, $occurredAt]) {
            if (! isset($skus[$code])) {
                continue;
            }
            StockTxn::query()->create([
                'tenant_id' => $owner->tenant_id,
                'biz_type' => $bizType,
                'biz_order_type' => null,
                'biz_order_id' => null,
                'stock_owner_id' => $owner->id,
                'location_id' => $location->id,
                'sku_id' => $skus[$code]->id,
                'qty_change' => $qty,
                'unit_cost_cents' => $cost,
                'amount_cents' => $cost * $qty,
                'direction' => $direction,
                'occurred_at' => $occurredAt,
                'operator_id' => $operator,
                'meta_json' => null,
            ]);
        }
    }

    private function resolveOperator(string $tenantId): string
    {
        $m = Membership::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->whereNull('store_id')->first();
        if ($m) {
            return $m->user_id;
        }
        $m = Membership::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->first();
        if ($m) {
            return $m->user_id;
        }
        // fallback 极端情况：返回 tenant_id 占位（schema 是 char(26)）
        return $tenantId;
    }
}
