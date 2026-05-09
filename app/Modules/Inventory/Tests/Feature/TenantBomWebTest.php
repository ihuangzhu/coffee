<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
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
        'store_id' => null,
    ]);
    test()->actingAs($user, 'web')
        ->withSession(['current_tenant_id' => $tenant->id]);
    app(CurrentTenant::class)->set($tenant->id);
    return $user;
}

test('GET /tenant/boms 列表只展示当前 tenant 的 BOM', function () {
    $t1 = Tenant::factory()->create();
    $t2 = Tenant::factory()->create();
    actingAsTenantUser($t1);

    $sku1 = makeSku($t1->id, 'SALE_PRODUCT');
    Bom::factory()->create(['tenant_id' => $t1->id, 'output_sku_id' => $sku1->id]);

    app(CurrentTenant::class)->set($t2->id);
    $sku2 = makeSku($t2->id, 'SALE_PRODUCT');
    Bom::factory()->create(['tenant_id' => $t2->id, 'output_sku_id' => $sku2->id]);

    app(CurrentTenant::class)->set($t1->id);
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

    app(CurrentTenant::class)->set($other->id);
    $crossSku = makeSku($other->id, 'SALE_PRODUCT');
    app(CurrentTenant::class)->set($tenant->id);

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

test('GET /tenant/boms 即使 CurrentTenant 未设置仍按 session tenant 隔离', function () {
    $t1 = Tenant::factory()->create();
    $t2 = Tenant::factory()->create();
    actingAsTenantUser($t1);  // sets session current_tenant_id = t1
    $sku1 = makeSku($t1->id, 'SALE_PRODUCT');
    Bom::factory()->create(['tenant_id' => $t1->id, 'output_sku_id' => $sku1->id]);

    app(\App\Support\Tenancy\CurrentTenant::class)->set(null);  // 故意清空 CurrentTenant

    $sku2 = makeSku($t2->id, 'SALE_PRODUCT');
    Bom::factory()->create(['tenant_id' => $t2->id, 'output_sku_id' => $sku2->id]);

    app(\App\Support\Tenancy\CurrentTenant::class)->set(null);  // 再次清空
    $resp = test()->get('/tenant/boms');
    $resp->assertOk();
    // 即使 CurrentTenant=null，控制器仍应按 session 中的 t1 过滤
    $resp->assertInertia(fn ($p) => $p->where('boms.total', 1));
});

test('编辑跨租户 BOM → 404', function () {
    $tenant = Tenant::factory()->create();
    $other = Tenant::factory()->create();
    actingAsTenantUser($tenant);

    app(CurrentTenant::class)->set($other->id);
    $output = makeSku($other->id, 'SALE_PRODUCT');
    $crossBom = Bom::factory()->create(['tenant_id' => $other->id, 'output_sku_id' => $output->id]);
    app(CurrentTenant::class)->set($tenant->id);

    test()->get('/tenant/boms/' . $crossBom->id . '/edit')->assertNotFound();
});
