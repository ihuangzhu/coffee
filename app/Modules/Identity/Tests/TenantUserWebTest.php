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
    // 显式 name/phone：避免 faker 随机生成的字符串误中后续测试里的 q=甲 / q=222 等模糊匹配
    $this->actor = User::factory()->create(['name' => '操作者', 'phone' => '15500000001']);
    Membership::factory()->create([
        'user_id' => $this->actor->id,
        'tenant_id' => $this->tenant->id,
        'store_id' => null,
    ]);
    // 必须把 actor 也作为该租户的 active 成员；登录后由 session 持有 current_tenant_id
    $this->actingAs($this->actor, 'web')
        ->withSession(['current_tenant_id' => $this->tenant->id]);
});

test('GET /tenant/users 列出当前租户成员（去重 user）', function () {
    $u1 = User::factory()->create(['name' => '甲']);
    $u2 = User::factory()->create(['name' => '乙']);
    Membership::factory()->create(['user_id' => $u1->id, 'tenant_id' => $this->tenant->id, 'store_id' => null]);
    Membership::factory()->create(['user_id' => $u2->id, 'tenant_id' => $this->tenant->id, 'store_id' => null]);
    // 别家租户成员不应出现
    $other = Tenant::factory()->create();
    $u3 = User::factory()->create();
    Membership::factory()->create(['user_id' => $u3->id, 'tenant_id' => $other->id, 'store_id' => null]);

    $this->get('/tenant/users')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant/Users/Index')
            ->where('total', 3) // actor + u1 + u2
            ->has('rows', 3)
        );
});

test('GET /tenant/users?q=甲 模糊匹配 name 与 phone', function () {
    $u1 = User::factory()->create(['name' => '甲', 'phone' => '13800000111']);
    $u2 = User::factory()->create(['name' => '乙', 'phone' => '13800000222']);
    Membership::factory()->create(['user_id' => $u1->id, 'tenant_id' => $this->tenant->id, 'store_id' => null]);
    Membership::factory()->create(['user_id' => $u2->id, 'tenant_id' => $this->tenant->id, 'store_id' => null]);

    $this->get('/tenant/users?q=甲')
        ->assertInertia(fn ($page) => $page->where('total', 1));
    $this->get('/tenant/users?q=222')
        ->assertInertia(fn ($page) => $page->where('total', 1));
});

test('POST /tenant/users 新建成员 + 可选角色绑定', function () {
    $role = Role::factory()->create([
        'tenant_id' => null, 'scope' => 'tenant', 'name' => '租户管理员', 'is_system' => true,
    ]);

    $this->post('/tenant/users', [
        'name' => '小王',
        'phone' => '13900000001',
        'password' => 'secret123',
        'tenant_role_id' => $role->id,
    ])->assertRedirect('/tenant/users');

    $u = User::query()->where('phone', '13900000001')->firstOrFail();
    expect(Membership::query()->withoutGlobalScopes()
        ->where('user_id', $u->id)
        ->where('tenant_id', $this->tenant->id)
        ->whereNull('store_id')
        ->where('status', 'active')->exists())->toBeTrue();
    expect(UserRoleBinding::query()->withoutGlobalScopes()
        ->where('user_id', $u->id)
        ->where('tenant_id', $this->tenant->id)
        ->where('role_id', $role->id)
        ->where('status', 'active')->exists())->toBeTrue();
});

test('POST /tenant/users 重复邀请相同租户级成员 422', function () {
    $u = User::factory()->create(['phone' => '13900000088']);
    Membership::factory()->create([
        'user_id' => $u->id, 'tenant_id' => $this->tenant->id, 'store_id' => null,
    ]);

    $this->post('/tenant/users', [
        'name' => 'X', 'phone' => '13900000088', 'password' => 'secret123',
    ])->assertSessionHasErrors('phone');
});

test('PATCH /tenant/users/{id} 改名 + 替换 tenant_role_id 写入新 binding 旧的 revoked', function () {
    $u = User::factory()->create(['name' => '旧名']);
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $this->tenant->id, 'store_id' => null]);
    $oldRole = Role::factory()->create(['tenant_id' => null, 'scope' => 'tenant', 'name' => '旧角色']);
    $newRole = Role::factory()->create(['tenant_id' => null, 'scope' => 'tenant', 'name' => '新角色']);
    UserRoleBinding::factory()->create([
        'user_id' => $u->id, 'role_id' => $oldRole->id, 'tenant_id' => $this->tenant->id, 'store_id' => null, 'status' => 'active',
    ]);

    $this->patch("/tenant/users/{$u->id}", [
        'name' => '新名',
        'tenant_role_id' => $newRole->id,
    ])->assertRedirect();

    $u->refresh();
    expect($u->name)->toBe('新名');
    expect(UserRoleBinding::query()->withoutGlobalScopes()
        ->where('user_id', $u->id)->where('role_id', $oldRole->id)
        ->where('status', 'revoked')->exists())->toBeTrue();
    expect(UserRoleBinding::query()->withoutGlobalScopes()
        ->where('user_id', $u->id)->where('role_id', $newRole->id)
        ->where('status', 'active')->exists())->toBeTrue();
});

test('DELETE /tenant/users/{id} 关闭 membership + 撤销 role binding', function () {
    $u = User::factory()->create();
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $this->tenant->id, 'store_id' => null]);
    $role = Role::factory()->create(['tenant_id' => null, 'scope' => 'tenant']);
    UserRoleBinding::factory()->create([
        'user_id' => $u->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id, 'store_id' => null, 'status' => 'active',
    ]);

    $this->delete("/tenant/users/{$u->id}")->assertRedirect();

    expect(Membership::query()->withoutGlobalScopes()
        ->where('user_id', $u->id)->where('status', 'left')->exists())->toBeTrue();
    expect(UserRoleBinding::query()->withoutGlobalScopes()
        ->where('user_id', $u->id)->where('status', 'revoked')->exists())->toBeTrue();
});

test('POST /tenant/users/{id}/reset-password 改密码', function () {
    $u = User::factory()->create();
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $this->tenant->id, 'store_id' => null]);
    $oldHash = $u->password;

    $this->post("/tenant/users/{$u->id}/reset-password", ['password' => 'newsecret123'])
        ->assertRedirect();

    $u->refresh();
    expect($u->password)->not->toBe($oldHash);
});

test('GET /tenant/users 未选定租户 422', function () {
    $this->withSession(['current_tenant_id' => null]);
    $this->get('/tenant/users')->assertSessionHasErrors('tenant');
});
