<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Good;
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
    $this->category = Category::factory()->create(['tenant_id' => $this->tenant->id, 'name' => '咖啡']);
    $this->actingAs($this->actor, 'web')
        ->withSession(['current_tenant_id' => $this->tenant->id]);
});

test('GET /tenant/goods 列出 + has_categories=true', function () {
    Good::factory()->create([
        'tenant_id' => $this->tenant->id, 'category_id' => $this->category->id,
        'name' => '美式', 'sku_base_price_cents' => 2200, 'status' => 'active',
    ]);

    $this->get('/tenant/goods')
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('tenant/Goods/Index')
            ->where('total', 1)
            ->where('has_categories', true)
            ->where('rows.0.name', '美式')
            ->where('rows.0.category_name', '咖啡')
            ->where('rows.0.sku_base_price_cents', 2200)
        );
});

test('GET /tenant/goods?q=美 + status=active 过滤生效', function () {
    Good::factory()->create(['tenant_id' => $this->tenant->id, 'category_id' => $this->category->id, 'name' => '美式', 'status' => 'active']);
    Good::factory()->create(['tenant_id' => $this->tenant->id, 'category_id' => $this->category->id, 'name' => '拿铁', 'status' => 'active']);
    Good::factory()->create(['tenant_id' => $this->tenant->id, 'category_id' => $this->category->id, 'name' => '美式下架', 'status' => 'off_shelf']);

    $this->get('/tenant/goods?q=美&status=active')
        ->assertInertia(fn ($p) => $p->where('total', 1));
});

test('POST /tenant/goods 接受嵌套 sku payload', function () {
    $this->post('/tenant/goods', [
        'name' => '美式', 'category_id' => $this->category->id,
        'sku' => ['base_price_cents' => 2200, 'code' => 'SKU-AM01', 'attrs_json' => null],
    ])->assertRedirect('/tenant/goods');

    $g = Good::query()->withoutGlobalScopes()->where('name', '美式')->firstOrFail();
    expect($g->sku_base_price_cents)->toBe(2200);
    expect($g->sku_code)->toBe('SKU-AM01');
    expect($g->status->value)->toBe('active');
});

test('POST 同租户 sku_code 重复 422', function () {
    Good::factory()->create([
        'tenant_id' => $this->tenant->id, 'category_id' => $this->category->id, 'sku_code' => 'DUP',
    ]);

    $this->post('/tenant/goods', [
        'name' => 'X', 'category_id' => $this->category->id,
        'sku' => ['base_price_cents' => 100, 'code' => 'DUP', 'attrs_json' => null],
    ])->assertSessionHasErrors('sku.code');
});

test('POST 跨租户的 category_id 被拒（exists 限定 tenant_id）', function () {
    $other = Tenant::factory()->create();
    $alien = Category::factory()->create(['tenant_id' => $other->id]);

    $this->post('/tenant/goods', [
        'name' => 'X', 'category_id' => $alien->id,
        'sku' => ['base_price_cents' => 100, 'code' => null, 'attrs_json' => null],
    ])->assertSessionHasErrors('category_id');
});

test('PATCH /tenant/goods/{id} 切换状态（toggle）', function () {
    $g = Good::factory()->create([
        'tenant_id' => $this->tenant->id, 'category_id' => $this->category->id,
        'name' => 'X', 'sku_base_price_cents' => 100, 'sku_code' => null, 'status' => 'active',
    ]);

    $this->patch("/tenant/goods/{$g->id}", [
        'name' => 'X', 'category_id' => $this->category->id, 'status' => 'off_shelf',
        'sku' => ['base_price_cents' => 100, 'code' => null, 'attrs_json' => null],
    ])->assertRedirect();

    $g->refresh();
    expect($g->status->value)->toBe('off_shelf');
});

test('PATCH 跨租户 404', function () {
    $other = Tenant::factory()->create();
    $oc = Category::factory()->create(['tenant_id' => $other->id]);
    $g = Good::factory()->create(['tenant_id' => $other->id, 'category_id' => $oc->id]);

    $this->patch("/tenant/goods/{$g->id}", [
        'name' => 'X', 'category_id' => $this->category->id, 'status' => 'active',
        'sku' => ['base_price_cents' => 100, 'code' => null, 'attrs_json' => null],
    ])->assertNotFound();
});
