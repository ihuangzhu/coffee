<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Catalog\Models\ProductInventoryPolicy;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
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

/**
 * 创建一个 SKU 并为其建立 ProductInventoryPolicy（inventory_track_type=FINISHED_GOOD），
 * 以便 InventoryGuard::assertEnabled 不会拒绝该 SKU。
 */
function makeProduceWebSku(string $tenantId, string $itemType): ItemSku
{
    $cat = Category::factory()->create([
        'tenant_id' => $tenantId, 'owner_type' => 'TENANT', 'owner_store_id' => null,
    ]);
    $item = Item::factory()->create([
        'tenant_id'             => $tenantId,
        'item_type'             => $itemType,
        'business_category_id'  => $cat->id,
    ]);
    $sku = ItemSku::factory()->create(['tenant_id' => $tenantId, 'item_id' => $item->id]);

    ProductInventoryPolicy::query()->withoutGlobalScopes()->updateOrCreate(
        ['sku_id' => $sku->id],
        [
            'tenant_id'             => $tenantId,
            'inventory_track_type'  => 'FINISHED_GOOD',
            'stock_deduct_mode'     => 'MANUAL_DEDUCT',
            'allow_negative_stock'  => false,
        ],
    );

    return $sku;
}

/**
 * 登录一个 tenant 域用户（auth 中间件 + session tenant 绑定）。
 */
function loginTenantUser(Tenant $tenant): User
{
    $user = User::factory()->create();
    Membership::factory()->create([
        'user_id'   => $user->id,
        'tenant_id' => $tenant->id,
        'store_id'  => null,
    ]);
    test()->actingAs($user, 'web')
        ->withSession(['current_tenant_id' => $tenant->id]);
    app(CurrentTenant::class)->set($tenant->id);
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
    $raw    = makeProduceWebSku($tenant->id, 'RAW_MATERIAL');

    $bom = Bom::factory()->create([
        'tenant_id'    => $tenant->id,
        'output_sku_id' => $output->id,
        'output_qty'   => '1',
        'bom_type'     => 'STANDARD',
    ]);
    BomComponent::factory()->create([
        'bom_id'           => $bom->id,
        'component_sku_id' => $raw->id,
        'consume_qty'      => '5',
        'loss_rate'        => '0',
    ]);

    $owner = StockOwner::query()->withoutGlobalScopes()
        ->where('owner_ref_id', $store->id)->firstOrFail();
    $loc = StockLocation::query()->withoutGlobalScopes()
        ->where('stock_owner_id', $owner->id)->where('location_code', 'DEFAULT')->firstOrFail();
    StockBalance::query()->withoutGlobalScopes()->create([
        'tenant_id'      => $tenant->id,
        'stock_owner_id' => $owner->id,
        'location_id'    => $loc->id,
        'sku_id'         => $raw->id,
        'available_qty'  => '100',
    ]);

    test()->post('/tenant/produce', [
        'store_id'  => $store->id,
        'bom_id'    => $bom->id,
        'batch_qty' => 2,
    ])->assertRedirect('/tenant/produce')->assertSessionHas('success');

    expect(StockTxn::query()->where('biz_type', 'PRODUCTION_OUTPUT')->count())->toBe(1);
    expect(StockTxn::query()->where('biz_type', 'PRODUCTION_CONSUME')->count())->toBe(1);
});

test('GET /tenant/produce/preview 返回 output + consumes 含 sufficient flag', function () {
    $tenant = Tenant::factory()->create();
    $store  = Store::factory()->create(['tenant_id' => $tenant->id]);
    loginTenantUser($tenant);

    $output = makeProduceWebSku($tenant->id, 'SALE_PRODUCT');
    $raw    = makeProduceWebSku($tenant->id, 'RAW_MATERIAL');

    $bom = Bom::factory()->create([
        'tenant_id'    => $tenant->id,
        'output_sku_id' => $output->id,
        'output_qty'   => '1',
    ]);
    BomComponent::factory()->create([
        'bom_id'           => $bom->id,
        'component_sku_id' => $raw->id,
        'consume_qty'      => '5',
        'loss_rate'        => '0.1',
    ]);

    $resp = test()->getJson(
        '/tenant/produce/preview?store_id=' . $store->id .
        '&bom_id=' . $bom->id . '&batch_qty=2'
    );

    $resp->assertOk();
    $resp->assertJsonPath('output.qty', '2.0000');
    $resp->assertJsonPath('consumes.0.needed', '11.0000');   // 5 × 1.1 × 2
    $resp->assertJsonPath('consumes.0.available', '0');
    $resp->assertJsonPath('consumes.0.sufficient', false);
});

test('GET /tenant/produce/preview 跨租户 bom_id → 404', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $storeB  = Store::factory()->create(['tenant_id' => $tenantB->id]);
    loginTenantUser($tenantB);

    app(CurrentTenant::class)->set($tenantA->id);
    $crossSku = makeProduceWebSku($tenantA->id, 'SALE_PRODUCT');
    $crossBom = Bom::factory()->create(['tenant_id' => $tenantA->id, 'output_sku_id' => $crossSku->id]);

    app(CurrentTenant::class)->set($tenantB->id);
    test()->getJson(
        '/tenant/produce/preview?store_id=' . $storeB->id .
        '&bom_id=' . $crossBom->id . '&batch_qty=1'
    )->assertNotFound();
});

test('POST /tenant/produce 跨租户 bom_id → 404', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $storeB  = Store::factory()->create(['tenant_id' => $tenantB->id]);
    loginTenantUser($tenantB);

    app(CurrentTenant::class)->set($tenantA->id);
    $crossSku = makeProduceWebSku($tenantA->id, 'SALE_PRODUCT');
    $crossBom = Bom::factory()->create(['tenant_id' => $tenantA->id, 'output_sku_id' => $crossSku->id]);

    app(CurrentTenant::class)->set($tenantB->id);
    test()->post('/tenant/produce', [
        'store_id'  => $storeB->id,
        'bom_id'    => $crossBom->id,
        'batch_qty' => 1,
    ])->assertStatus(404);
});

test('POST /tenant/produce 跨租户 store_id → 422', function () {
    $tenantA    = Tenant::factory()->create();
    $tenantB    = Tenant::factory()->create();
    $crossStore = Store::factory()->create(['tenant_id' => $tenantA->id]);
    loginTenantUser($tenantB);

    $output = makeProduceWebSku($tenantB->id, 'SALE_PRODUCT');
    $bom    = Bom::factory()->create(['tenant_id' => $tenantB->id, 'output_sku_id' => $output->id]);

    test()->post('/tenant/produce', [
        'store_id'  => $crossStore->id,
        'bom_id'    => $bom->id,
        'batch_qty' => 1,
    ])->assertSessionHasErrors('store_id');
});
