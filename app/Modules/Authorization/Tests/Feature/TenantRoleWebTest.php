<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\Models\UserRoleBinding;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['name' => 'A咖啡']);
    $this->actor = User::factory()->create();
    Membership::factory()->create([
        'user_id' => $this->actor->id, 'tenant_id' => $this->tenant->id, 'store_id' => null,
    ]);
    $this->actingAs($this->actor, 'web')
        ->withSession(['current_tenant_id' => $this->tenant->id]);
});

test('GET /tenant/roles 同时返回本租户角色 + 系统全局模板，且统计在用人数', function () {
    // 系统模板（tenant_id NULL, is_system=true）
    $sys = Role::factory()->create(['tenant_id' => null, 'scope' => 'tenant', 'is_system' => true, 'name' => '租户管理员']);
    // 本租户自建
    $own = Role::factory()->create(['tenant_id' => $this->tenant->id, 'scope' => 'store', 'is_system' => false, 'name' => '收银员']);
    // 别家租户角色不应出现
    $other = Tenant::factory()->create();
    Role::factory()->create(['tenant_id' => $other->id, 'scope' => 'tenant', 'name' => '别家角色']);

    // 给 own 加 1 个活跃 binding
    $u = User::factory()->create();
    UserRoleBinding::factory()->create([
        'user_id' => $u->id, 'role_id' => $own->id,
        'tenant_id' => $this->tenant->id, 'store_id' => null, 'status' => 'active',
    ]);

    // 3 个系统模板（迁移种子：TenantAdmin/StoreManager/StoreClerk）+ 1 个测试系统 + 1 个本租户自建 = 5；别家租户的不应出现
    $this->get('/tenant/roles')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant/Roles/Index')
            ->has('roles', 5)
            ->where('roles.0.is_system', true) // is_system 排在前
        );
});

test('POST /tenant/roles 新建本租户角色', function () {
    $this->post('/tenant/roles', [
        'name' => '收银员',
        'scope' => 'store',
        'permissions' => ['users.read'],
    ])->assertRedirect('/tenant/roles');

    $r = Role::query()->where('tenant_id', $this->tenant->id)->where('name', '收银员')->firstOrFail();
    expect($r->scope)->toBe('store');
    expect($r->is_system)->toBeFalse();
    expect($r->permissions)->toBe(['users.read']);
});

test('POST /tenant/roles 拒绝非法 permission', function () {
    $this->post('/tenant/roles', [
        'name' => 'X', 'scope' => 'tenant',
        'permissions' => ['platform.bogus'],
    ])->assertSessionHasErrors('permissions.0');
});

test('PATCH /tenant/roles/{id} 更新名称与权限', function () {
    $r = Role::factory()->create([
        'tenant_id' => $this->tenant->id, 'scope' => 'store', 'name' => '旧名', 'permissions' => [],
    ]);

    $this->patch("/tenant/roles/{$r->id}", [
        'name' => '新名', 'scope' => 'store', 'permissions' => ['roles.read', 'users.read'],
    ])->assertRedirect();

    $r->refresh();
    expect($r->name)->toBe('新名');
    expect($r->permissions)->toBe(['roles.read', 'users.read']);
});

test('PATCH /tenant/roles/{id} 系统模板返回 404', function () {
    $sys = Role::factory()->create(['tenant_id' => null, 'is_system' => true, 'scope' => 'tenant']);

    $this->patch("/tenant/roles/{$sys->id}", [
        'name' => 'X', 'scope' => 'tenant', 'permissions' => [],
    ])->assertNotFound();
});

test('PATCH /tenant/roles/{id} 跨租户角色返回 404', function () {
    $other = Tenant::factory()->create();
    $r = Role::factory()->create(['tenant_id' => $other->id, 'scope' => 'tenant', 'is_system' => false]);

    $this->patch("/tenant/roles/{$r->id}", [
        'name' => 'X', 'scope' => 'tenant', 'permissions' => [],
    ])->assertNotFound();
});

test('DELETE /tenant/roles/{id} 在用拒绝 422', function () {
    $r = Role::factory()->create(['tenant_id' => $this->tenant->id, 'scope' => 'tenant', 'is_system' => false]);
    $u = User::factory()->create();
    UserRoleBinding::factory()->create([
        'user_id' => $u->id, 'role_id' => $r->id,
        'tenant_id' => $this->tenant->id, 'store_id' => null, 'status' => 'active',
    ]);

    $this->delete("/tenant/roles/{$r->id}")->assertSessionHasErrors('role');
    expect(Role::query()->whereKey($r->id)->exists())->toBeTrue();
});

test('DELETE /tenant/roles/{id} 闲置删除成功', function () {
    $r = Role::factory()->create(['tenant_id' => $this->tenant->id, 'scope' => 'tenant', 'is_system' => false]);

    $this->delete("/tenant/roles/{$r->id}")->assertRedirect();
    expect(Role::query()->whereKey($r->id)->exists())->toBeFalse();
});
