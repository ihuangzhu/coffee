<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\Models\UserRoleBinding;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->store = Store::factory()->create(['tenant_id' => $this->tenant->id, 'name' => '徐汇店']);
    $this->actor = User::factory()->create(['name' => '操作者', 'phone' => '15500000001']);
    Membership::factory()->create([
        'user_id' => $this->actor->id, 'tenant_id' => $this->tenant->id, 'store_id' => null,
    ]);
    $this->actingAs($this->actor, 'web')
        ->withSession(['current_tenant_id' => $this->tenant->id]);
});

test('GET /tenant/stores/{id}/users 列出门店活跃成员', function () {
    $u = User::factory()->create(['name' => '小红']);
    Membership::factory()->create([
        'user_id' => $u->id, 'tenant_id' => $this->tenant->id, 'store_id' => $this->store->id,
    ]);
    $role = Role::factory()->create(['tenant_id' => null, 'scope' => 'store', 'name' => '收银员', 'is_system' => true]);
    UserRoleBinding::factory()->create([
        'user_id' => $u->id, 'role_id' => $role->id,
        'tenant_id' => $this->tenant->id, 'store_id' => $this->store->id, 'status' => 'active',
    ]);

    $this->get("/tenant/stores/{$this->store->id}/users")
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('tenant/StoreUsers/Index')
            ->where('store.id', $this->store->id)
            ->where('total', 1)
            ->has('rows', 1)
            ->where('rows.0.user_id', $u->id)
            ->where('rows.0.role_name', '收银员')
        );
});

test('POST /tenant/stores/{id}/users 加入门店 + 写 binding', function () {
    $u = User::factory()->create();
    Membership::factory()->create([
        'user_id' => $u->id, 'tenant_id' => $this->tenant->id, 'store_id' => null,
    ]);
    $role = Role::factory()->create(['tenant_id' => null, 'scope' => 'store']);

    $this->post("/tenant/stores/{$this->store->id}/users", [
        'user_id' => $u->id, 'role_id' => $role->id,
    ])->assertRedirect("/tenant/stores/{$this->store->id}/users");

    expect(Membership::query()->withoutGlobalScopes()
        ->where('user_id', $u->id)->where('store_id', $this->store->id)
        ->where('status', 'active')->exists())->toBeTrue();
    expect(UserRoleBinding::query()->withoutGlobalScopes()
        ->where('user_id', $u->id)->where('store_id', $this->store->id)
        ->where('role_id', $role->id)->where('status', 'active')->exists())->toBeTrue();
});

test('POST /tenant/stores/{id}/users 用户不是租户成员 422', function () {
    $u = User::factory()->create(); // 没 membership
    $role = Role::factory()->create(['tenant_id' => null, 'scope' => 'store']);

    $this->post("/tenant/stores/{$this->store->id}/users", [
        'user_id' => $u->id, 'role_id' => $role->id,
    ])->assertSessionHasErrors('user_id');
});

test('POST /tenant/stores/{id}/users role 是 tenant scope 422', function () {
    $u = User::factory()->create();
    Membership::factory()->create([
        'user_id' => $u->id, 'tenant_id' => $this->tenant->id, 'store_id' => null,
    ]);
    $role = Role::factory()->create(['tenant_id' => null, 'scope' => 'tenant']);

    $this->post("/tenant/stores/{$this->store->id}/users", [
        'user_id' => $u->id, 'role_id' => $role->id,
    ])->assertSessionHasErrors('role_id');
});

test('PATCH 改 role：旧 active binding 被 revoke、新 active 写入', function () {
    $u = User::factory()->create();
    Membership::factory()->create([
        'user_id' => $u->id, 'tenant_id' => $this->tenant->id, 'store_id' => $this->store->id,
    ]);
    $oldRole = Role::factory()->create(['tenant_id' => null, 'scope' => 'store']);
    $newRole = Role::factory()->create(['tenant_id' => null, 'scope' => 'store']);
    UserRoleBinding::factory()->create([
        'user_id' => $u->id, 'role_id' => $oldRole->id,
        'tenant_id' => $this->tenant->id, 'store_id' => $this->store->id, 'status' => 'active',
    ]);

    $this->patch("/tenant/stores/{$this->store->id}/users/{$u->id}", ['role_id' => $newRole->id])
        ->assertRedirect();

    expect(UserRoleBinding::query()->withoutGlobalScopes()
        ->where('user_id', $u->id)->where('role_id', $oldRole->id)
        ->where('status', 'revoked')->exists())->toBeTrue();
    expect(UserRoleBinding::query()->withoutGlobalScopes()
        ->where('user_id', $u->id)->where('role_id', $newRole->id)
        ->where('status', 'active')->exists())->toBeTrue();
});

test('DELETE 移出门店：membership=left + binding=revoked', function () {
    $u = User::factory()->create();
    Membership::factory()->create([
        'user_id' => $u->id, 'tenant_id' => $this->tenant->id, 'store_id' => $this->store->id,
    ]);
    $role = Role::factory()->create(['tenant_id' => null, 'scope' => 'store']);
    UserRoleBinding::factory()->create([
        'user_id' => $u->id, 'role_id' => $role->id,
        'tenant_id' => $this->tenant->id, 'store_id' => $this->store->id, 'status' => 'active',
    ]);

    $this->delete("/tenant/stores/{$this->store->id}/users/{$u->id}")->assertRedirect();

    expect(Membership::query()->withoutGlobalScopes()
        ->where('user_id', $u->id)->where('store_id', $this->store->id)
        ->where('status', 'left')->exists())->toBeTrue();
    expect(UserRoleBinding::query()->withoutGlobalScopes()
        ->where('user_id', $u->id)->where('store_id', $this->store->id)
        ->where('status', 'revoked')->exists())->toBeTrue();
});

test('GET 跨租户 store 404', function () {
    $other = Tenant::factory()->create();
    $store = Store::factory()->create(['tenant_id' => $other->id]);

    $this->get("/tenant/stores/{$store->id}/users")->assertNotFound();
});
