<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Catalog\Models\ProductInventoryPolicy;
use App\Modules\Identity\Models\User;
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
 * @return array{0: Bom, 1: StockOwner, 2: StockLocation}
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
    app(CurrentTenant::class)->set($tenant->id);

    // 确保产出和每个组件 sku 的 policy.inventory_track_type != NONE，否则 InventoryGuard 拒绝
    // ItemSkuObserver 在创建时自动创建 policy (track_type=FINISHED_GOOD)，用 updateOrCreate 覆盖
    foreach (array_merge([['sku' => $output]], $components) as $row) {
        ProductInventoryPolicy::query()->withoutGlobalScopes()
            ->updateOrCreate(
                ['sku_id' => $row['sku']->id],
                [
                    'tenant_id'              => $tenant->id,
                    'inventory_track_type'   => 'FINISHED_GOOD',
                    'stock_deduct_mode'      => 'MANUAL_DEDUCT',
                    'allow_negative_stock'   => false,
                ],
            );
    }

    $bom = Bom::factory()->create([
        'tenant_id'     => $tenant->id,
        'output_sku_id' => $output->id,
        'output_qty'    => $outputQty,
        'bom_type'      => $bomType,
        'store_id'      => $storeIdOnBom,
    ]);

    foreach ($components as $i => $row) {
        BomComponent::factory()->create([
            'bom_id'           => $bom->id,
            'component_sku_id' => $row['sku']->id,
            'consume_qty'      => (string) $row['consume'],
            'loss_rate'        => (string) ($row['loss'] ?? '0'),
            'sequence_no'      => $i,
        ]);
    }

    $owner = StockOwner::query()->withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('owner_type', 'STORE')
        ->where('owner_ref_id', $store->id)
        ->firstOrFail();

    $location = StockLocation::query()->withoutGlobalScopes()
        ->where('stock_owner_id', $owner->id)
        ->where('location_code', 'DEFAULT')
        ->firstOrFail();

    return [$bom, $owner, $location];
}

/**
 * 为测试创建一个指定 item_type 的 SKU（含 category + item）。
 */
function makeProduceSku(string $tenantId, string $itemType): ItemSku
{
    $cat = Category::factory()->create([
        'tenant_id'       => $tenantId,
        'owner_type'      => 'TENANT',
        'owner_store_id'  => null,
    ]);
    $item = Item::factory()->create([
        'tenant_id'            => $tenantId,
        'item_type'            => $itemType,
        'business_category_id' => $cat->id,
    ]);
    return ItemSku::factory()->create([
        'tenant_id' => $tenantId,
        'item_id'   => $item->id,
    ]);
}

/**
 * 预置 StockBalance 行（available_qty = qty）。
 */
function preloadBalance(string $tenantId, string $ownerId, string $locId, string $skuId, string $qty): void
{
    StockBalance::query()->withoutGlobalScopes()->create([
        'tenant_id'      => $tenantId,
        'stock_owner_id' => $ownerId,
        'location_id'    => $locId,
        'sku_id'         => $skuId,
        'available_qty'  => $qty,
    ]);
}

// ─── 正常路径 ────────────────────────────────────────────────────────────────

test('正常路径：1 component, loss=0.1, batch=5 → consume -55, output +5', function () {
    $tenant = Tenant::factory()->create();
    $store  = Store::factory()->create(['tenant_id' => $tenant->id]);
    $user   = User::factory()->create();

    $output = makeProduceSku($tenant->id, 'SALE_PRODUCT');
    $rawA   = makeProduceSku($tenant->id, 'RAW_MATERIAL');

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
    $store  = Store::factory()->create(['tenant_id' => $tenant->id]);
    $user   = User::factory()->create();
    $output = makeProduceSku($tenant->id, 'SALE_PRODUCT');
    $raw    = makeProduceSku($tenant->id, 'RAW_MATERIAL');

    [$bom, $owner, $loc] = setupProduceScene($tenant, $store, $output, '0.5', [
        ['sku' => $raw, 'consume' => '2', 'loss' => '0'],
    ]);
    preloadBalance($tenant->id, $owner->id, $loc->id, $raw->id, '100');

    $result = ProduceAction::handle($tenant->id, $store->id, $bom->id, '4', $user->id);

    $outBal = StockBalance::query()->withoutGlobalScopes()
        ->where('sku_id', $output->id)->first();
    expect((string) $outBal->available_qty)->toBe('2.0000');
});

// ─── 负库存 ──────────────────────────────────────────────────────────────────

test('component balance 不存在 + 不允许负库存 → INSUFFICIENT_STOCK 抛出且无写入', function () {
    $tenant = Tenant::factory()->create();
    $store  = Store::factory()->create(['tenant_id' => $tenant->id]);
    $user   = User::factory()->create();
    $output = makeProduceSku($tenant->id, 'SALE_PRODUCT');
    $raw    = makeProduceSku($tenant->id, 'RAW_MATERIAL');

    [$bom] = setupProduceScene($tenant, $store, $output, '1', [
        ['sku' => $raw, 'consume' => '10', 'loss' => '0'],
    ]);

    expect(fn () => ProduceAction::handle($tenant->id, $store->id, $bom->id, '1', $user->id))
        ->toThrow(BusinessException::class, '库存不足');

    expect(StockTxn::query()->count())->toBe(0);
});

test('component balance=0 + 允许负库存 → 创建一行负 balance', function () {
    $tenant = Tenant::factory()->create();
    $store  = Store::factory()->create(['tenant_id' => $tenant->id]);
    $user   = User::factory()->create();

    // 启用 tenant + policy 双允许负库存
    TenantInventoryConfig::query()->withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->update(['negative_stock_allowed' => true]);

    $output = makeProduceSku($tenant->id, 'SALE_PRODUCT');
    $raw    = makeProduceSku($tenant->id, 'RAW_MATERIAL');

    [$bom, $owner, $loc] = setupProduceScene($tenant, $store, $output, '1', [
        ['sku' => $raw, 'consume' => '5', 'loss' => '0'],
    ]);

    ProductInventoryPolicy::query()->withoutGlobalScopes()
        ->where('sku_id', $raw->id)
        ->update(['allow_negative_stock' => true]);

    ProduceAction::handle($tenant->id, $store->id, $bom->id, '2', $user->id);

    $rawBal = StockBalance::query()->withoutGlobalScopes()
        ->where('sku_id', $raw->id)->first();
    expect((string) $rawBal->available_qty)->toBe('-10.0000');
});

// ─── 租户 / BOM 隔离 ─────────────────────────────────────────────────────────

test('跨租户 BOM → BOM_NOT_FOUND', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $store   = Store::factory()->create(['tenant_id' => $tenantB->id]);
    $user    = User::factory()->create();

    $output = makeProduceSku($tenantA->id, 'SALE_PRODUCT');
    $raw    = makeProduceSku($tenantA->id, 'RAW_MATERIAL');

    [$bom] = setupProduceScene(
        $tenantA,
        Store::factory()->create(['tenant_id' => $tenantA->id]),
        $output,
        '1',
        [['sku' => $raw, 'consume' => '1', 'loss' => '0']]
    );

    expect(fn () => ProduceAction::handle($tenantB->id, $store->id, $bom->id, '1', $user->id))
        ->toThrow(BusinessException::class, '配方不存在');
});

test('STORE_CUSTOM bom 与传入 store_id 不一致 → BOM_STORE_MISMATCH', function () {
    $tenant = Tenant::factory()->create();
    $storeA = Store::factory()->create(['tenant_id' => $tenant->id]);
    $storeB = Store::factory()->create(['tenant_id' => $tenant->id]);
    $user   = User::factory()->create();

    $output = makeProduceSku($tenant->id, 'SALE_PRODUCT');
    $raw    = makeProduceSku($tenant->id, 'RAW_MATERIAL');

    [$bom] = setupProduceScene(
        $tenant, $storeA, $output, '1',
        [['sku' => $raw, 'consume' => '1', 'loss' => '0']],
        'STORE_CUSTOM', $storeA->id
    );

    expect(fn () => ProduceAction::handle($tenant->id, $storeB->id, $bom->id, '1', $user->id))
        ->toThrow(BusinessException::class, '门店与配方不匹配');
});

// ─── InventoryGuard toggle ────────────────────────────────────────────────────

test('output_sku toggle 关闭 → InventoryDisabledException', function () {
    $tenant = Tenant::factory()->create();
    $store  = Store::factory()->create(['tenant_id' => $tenant->id]);
    $user   = User::factory()->create();

    $output = makeProduceSku($tenant->id, 'SALE_PRODUCT');
    $raw    = makeProduceSku($tenant->id, 'RAW_MATERIAL');

    [$bom] = setupProduceScene($tenant, $store, $output, '1',
        [['sku' => $raw, 'consume' => '1', 'loss' => '0']]);

    // 把 output 的 policy.inventory_track_type 改为 NONE
    ProductInventoryPolicy::query()->withoutGlobalScopes()
        ->where('sku_id', $output->id)
        ->update(['inventory_track_type' => 'NONE']);

    expect(fn () => ProduceAction::handle($tenant->id, $store->id, $bom->id, '1', $user->id))
        ->toThrow(\App\Modules\Inventory\Exceptions\InventoryDisabledException::class);
});

test('component sku toggle 关闭 → InventoryDisabledException', function () {
    $tenant = Tenant::factory()->create();
    $store  = Store::factory()->create(['tenant_id' => $tenant->id]);
    $user   = User::factory()->create();

    $output = makeProduceSku($tenant->id, 'SALE_PRODUCT');
    $raw    = makeProduceSku($tenant->id, 'RAW_MATERIAL');

    [$bom] = setupProduceScene($tenant, $store, $output, '1',
        [['sku' => $raw, 'consume' => '1', 'loss' => '0']]);

    ProductInventoryPolicy::query()->withoutGlobalScopes()
        ->where('sku_id', $raw->id)
        ->update(['inventory_track_type' => 'NONE']);

    expect(fn () => ProduceAction::handle($tenant->id, $store->id, $bom->id, '1', $user->id))
        ->toThrow(\App\Modules\Inventory\Exceptions\InventoryDisabledException::class);
});

// ─── BOM 状态边界 ─────────────────────────────────────────────────────────────

test('bom 无 component → BOM_NO_COMPONENTS', function () {
    $tenant = Tenant::factory()->create();
    $store  = Store::factory()->create(['tenant_id' => $tenant->id]);
    $user   = User::factory()->create();
    app(CurrentTenant::class)->set($tenant->id);

    $output = makeProduceSku($tenant->id, 'SALE_PRODUCT');
    ProductInventoryPolicy::query()->withoutGlobalScopes()->updateOrCreate(
        ['sku_id' => $output->id],
        [
            'tenant_id'            => $tenant->id,
            'inventory_track_type' => 'FINISHED_GOOD',
            'stock_deduct_mode'    => 'MANUAL_DEDUCT',
            'allow_negative_stock' => false,
        ],
    );
    $bom = Bom::factory()->create([
        'tenant_id'     => $tenant->id,
        'output_sku_id' => $output->id,
    ]);

    expect(fn () => ProduceAction::handle($tenant->id, $store->id, $bom->id, '1', $user->id))
        ->toThrow(BusinessException::class, '配方无组件');
});

test('bom status=disabled → BOM_NOT_FOUND', function () {
    $tenant = Tenant::factory()->create();
    $store  = Store::factory()->create(['tenant_id' => $tenant->id]);
    $user   = User::factory()->create();

    $output = makeProduceSku($tenant->id, 'SALE_PRODUCT');
    $raw    = makeProduceSku($tenant->id, 'RAW_MATERIAL');

    [$bom] = setupProduceScene($tenant, $store, $output, '1',
        [['sku' => $raw, 'consume' => '1', 'loss' => '0']]);
    $bom->update(['status' => 'disabled']);

    expect(fn () => ProduceAction::handle($tenant->id, $store->id, $bom->id, '1', $user->id))
        ->toThrow(BusinessException::class, '配方不存在');
});

test('bom 软删 → BOM_NOT_FOUND', function () {
    $tenant = Tenant::factory()->create();
    $store  = Store::factory()->create(['tenant_id' => $tenant->id]);
    $user   = User::factory()->create();

    $output = makeProduceSku($tenant->id, 'SALE_PRODUCT');
    $raw    = makeProduceSku($tenant->id, 'RAW_MATERIAL');

    [$bom] = setupProduceScene($tenant, $store, $output, '1',
        [['sku' => $raw, 'consume' => '1', 'loss' => '0']]);
    $bom->delete();

    expect(fn () => ProduceAction::handle($tenant->id, $store->id, $bom->id, '1', $user->id))
        ->toThrow(BusinessException::class, '配方不存在');
});

// ─── 多 location ──────────────────────────────────────────────────────────────

test('source_location_id 与 output_location_id 不同 → 两条 txn 落对应 location', function () {
    $tenant = Tenant::factory()->create();
    $store  = Store::factory()->create(['tenant_id' => $tenant->id]);
    $user   = User::factory()->create();

    $output = makeProduceSku($tenant->id, 'SALE_PRODUCT');
    $raw    = makeProduceSku($tenant->id, 'RAW_MATERIAL');

    [$bom, $owner, $defaultLoc] = setupProduceScene($tenant, $store, $output, '1',
        [['sku' => $raw, 'consume' => '5', 'loss' => '0']]);

    // 额外建一个 location 当成品入库位
    $altLoc = StockLocation::query()->withoutGlobalScopes()->create([
        'tenant_id'      => $tenant->id,
        'stock_owner_id' => $owner->id,
        'location_code'  => 'KITCHEN',
        'location_name'  => '后厨',
        'location_type'  => 'BACKROOM',
    ]);

    preloadBalance($tenant->id, $owner->id, $defaultLoc->id, $raw->id, '100');

    $result = ProduceAction::handle(
        $tenant->id, $store->id, $bom->id, '1', $user->id,
        $defaultLoc->id, $altLoc->id
    );

    $consumeTxn = StockTxn::query()->find($result['consume_txn_ids'][0]);
    $outputTxn  = StockTxn::query()->find($result['output_txn_id']);
    expect($consumeTxn->location_id)->toBe($defaultLoc->id);
    expect($outputTxn->location_id)->toBe($altLoc->id);
});

// ─── 事件 ─────────────────────────────────────────────────────────────────────

test('成功提交后 emit StockChanged 事件 N+1 次', function () {
    Event::fake([StockChanged::class]);

    $tenant = Tenant::factory()->create();
    $store  = Store::factory()->create(['tenant_id' => $tenant->id]);
    $user   = User::factory()->create();

    $output = makeProduceSku($tenant->id, 'SALE_PRODUCT');
    $rawA   = makeProduceSku($tenant->id, 'RAW_MATERIAL');
    $rawB   = makeProduceSku($tenant->id, 'RAW_MATERIAL');

    [$bom, $owner, $loc] = setupProduceScene($tenant, $store, $output, '1', [
        ['sku' => $rawA, 'consume' => '5', 'loss' => '0'],
        ['sku' => $rawB, 'consume' => '3', 'loss' => '0'],
    ]);
    preloadBalance($tenant->id, $owner->id, $loc->id, $rawA->id, '100');
    preloadBalance($tenant->id, $owner->id, $loc->id, $rawB->id, '100');

    ProduceAction::handle($tenant->id, $store->id, $bom->id, '1', $user->id);

    // 1 PRODUCTION_OUTPUT + 2 PRODUCTION_CONSUME = 3
    Event::assertDispatchedTimes(StockChanged::class, 3);
});

// ─── 参数校验 ─────────────────────────────────────────────────────────────────

test('batchQty 无效（<=0）→ INVALID_BATCH_QTY', function () {
    $tenant = Tenant::factory()->create();
    $store  = Store::factory()->create(['tenant_id' => $tenant->id]);
    $user   = User::factory()->create();
    app(CurrentTenant::class)->set($tenant->id);
    $bom = Bom::factory()->create(['tenant_id' => $tenant->id]);

    expect(fn () => ProduceAction::handle($tenant->id, $store->id, $bom->id, '0', $user->id))
        ->toThrow(BusinessException::class, 'batchQty');
});
