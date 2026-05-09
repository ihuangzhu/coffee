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

    expect(Category::query()->withoutGlobalScopes()->withoutTrashed()->find($cat->id))->toBeNull();
    expect(Category::query()->withoutGlobalScopes()->withTrashed()->find($cat->id))
        ->not->toBeNull();
});

test('跨租户隔离：他租户分类不可访问', function () {
    $other = Tenant::factory()->create();
    $otherCat = Category::factory()->create(['tenant_id' => $other->id]);

    $this->get("/tenant/categories/{$otherCat->id}/edit")->assertNotFound();
});

test('PATCH 重父：descendants path/level 同步更新', function () {
    $rootA = Category::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'A']);
    $childA1 = Category::factory()->child($rootA)->create(['tenant_id' => $this->tenant->id, 'name' => 'A.1']);
    $grandchildA11 = Category::factory()->child($childA1)->create(['tenant_id' => $this->tenant->id, 'name' => 'A.1.1']);
    $rootB = Category::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'B']);

    $this->patch("/tenant/categories/{$rootA->id}", [
        'owner_type' => 'TENANT',
        'category_type' => 'BUSINESS',
        'item_type_scope' => 'ALL',
        'parent_id' => $rootB->id,
        'name' => 'A',
    ])->assertRedirect();

    $rootA->refresh();
    $childA1->refresh();
    $grandchildA11->refresh();

    expect($rootA->path)->toBe('/' . $rootB->id . '/');
    expect($rootA->level)->toBe(2);

    expect($childA1->path)->toBe('/' . $rootB->id . '/' . $rootA->id . '/');
    expect($childA1->level)->toBe(3);

    expect($grandchildA11->path)->toBe('/' . $rootB->id . '/' . $rootA->id . '/' . $childA1->id . '/');
    expect($grandchildA11->level)->toBe(4);
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
