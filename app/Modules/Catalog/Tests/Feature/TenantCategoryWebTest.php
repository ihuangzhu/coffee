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
        'user_id' => $this->actor->id, 'tenant_id' => $this->tenant->id, 'store_id' => null,
    ]);
    $this->actingAs($this->actor, 'web')
        ->withSession(['current_tenant_id' => $this->tenant->id]);
});

test('GET /tenant/categories 列出本租户分类', function () {
    Category::factory()->create(['tenant_id' => $this->tenant->id, 'name' => '咖啡', 'sort' => 1]);
    Category::factory()->create(['tenant_id' => $this->tenant->id, 'name' => '甜品', 'sort' => 2]);
    // 别家租户
    $other = Tenant::factory()->create();
    Category::factory()->create(['tenant_id' => $other->id]);

    $this->get('/tenant/categories')
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('tenant/Categories/Index')
            ->where('total', 2)
            ->has('rows', 2)
            ->where('rows.0.name', '咖啡')
        );
});

test('POST /tenant/categories 新建分类', function () {
    $this->post('/tenant/categories', ['name' => '冷饮', 'sort' => 5])
        ->assertRedirect('/tenant/categories');

    expect(Category::query()->withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->where('name', '冷饮')->where('sort', 5)->exists())->toBeTrue();
});

test('POST 同租户内 name 重复 422', function () {
    Category::factory()->create(['tenant_id' => $this->tenant->id, 'name' => '咖啡']);

    $this->post('/tenant/categories', ['name' => '咖啡'])->assertSessionHasErrors('name');
});

test('PATCH /tenant/categories/{id} 改名 + 排序', function () {
    $c = Category::factory()->create(['tenant_id' => $this->tenant->id, 'name' => '旧', 'sort' => 0]);

    $this->patch("/tenant/categories/{$c->id}", ['name' => '新', 'sort' => 9])
        ->assertRedirect();

    $c->refresh();
    expect($c->name)->toBe('新');
    expect((int) $c->sort)->toBe(9);
});

test('PATCH 跨租户 404', function () {
    $other = Tenant::factory()->create();
    $c = Category::factory()->create(['tenant_id' => $other->id]);

    $this->patch("/tenant/categories/{$c->id}", ['name' => 'X'])->assertNotFound();
});

test('DELETE 闲置删除', function () {
    $c = Category::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->delete("/tenant/categories/{$c->id}")->assertRedirect();
    expect(Category::query()->withoutGlobalScopes()->whereKey($c->id)->exists())->toBeFalse();
});
