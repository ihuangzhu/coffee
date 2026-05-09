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
