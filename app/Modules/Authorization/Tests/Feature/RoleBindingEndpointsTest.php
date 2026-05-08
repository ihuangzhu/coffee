<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\Models\UserRoleBinding;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function asUserWithAssignRole(): array
{
    $actor = User::factory()->create();
    $t = Tenant::factory()->create();
    Membership::factory()->create(['user_id' => $actor->id, 'tenant_id' => $t->id]);
    $role = Role::factory()->create(['tenant_id' => $t->id, 'permissions' => ['users.assign-role']]);
    UserRoleBinding::factory()->create(['user_id' => $actor->id, 'role_id' => $role->id, 'tenant_id' => $t->id]);
    Sanctum::actingAs($actor);
    return ['actor' => $actor, 'tenant' => $t];
}

test('POST 创建 tenant-scope 绑定（无 store_id）', function () {
    ['tenant' => $t] = asUserWithAssignRole();
    $target = User::factory()->create();
    Membership::factory()->create(['user_id' => $target->id, 'tenant_id' => $t->id]);
    $r = Role::factory()->create(['tenant_id' => $t->id, 'scope' => 'tenant', 'permissions' => ['roles.read']]);

    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->postJson("/api/users/{$target->id}/role-bindings", ['role_id' => $r->id]);

    $resp->assertStatus(201);
    expect($resp->json('binding.store_id'))->toBeNull();
});

test('POST 创建 store-scope 绑定（必带 store_id）', function () {
    ['tenant' => $t] = asUserWithAssignRole();
    $target = User::factory()->create();
    Membership::factory()->create(['user_id' => $target->id, 'tenant_id' => $t->id]);
    $store = Store::factory()->create(['tenant_id' => $t->id]);
    $r = Role::factory()->create(['tenant_id' => $t->id, 'scope' => 'store', 'permissions' => []]);

    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->postJson("/api/users/{$target->id}/role-bindings", [
            'role_id' => $r->id, 'store_id' => $store->id,
        ]);

    $resp->assertStatus(201);
    expect($resp->json('binding.store_id'))->toBe($store->id);
});

test('store-scope 角色但缺 store_id → 422 binding.store-id-required', function () {
    ['tenant' => $t] = asUserWithAssignRole();
    $target = User::factory()->create();
    Membership::factory()->create(['user_id' => $target->id, 'tenant_id' => $t->id]);
    $r = Role::factory()->create(['tenant_id' => $t->id, 'scope' => 'store']);

    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->postJson("/api/users/{$target->id}/role-bindings", ['role_id' => $r->id]);

    $resp->assertStatus(422);
    expect($resp->json('code'))->toBe('binding.store-id-required');
});

test('tenant-scope 角色但传了 store_id → 422 binding.store-id-not-allowed', function () {
    ['tenant' => $t] = asUserWithAssignRole();
    $target = User::factory()->create();
    Membership::factory()->create(['user_id' => $target->id, 'tenant_id' => $t->id]);
    $store = Store::factory()->create(['tenant_id' => $t->id]);
    $r = Role::factory()->create(['tenant_id' => $t->id, 'scope' => 'tenant']);

    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->postJson("/api/users/{$target->id}/role-bindings", [
            'role_id' => $r->id, 'store_id' => $store->id,
        ]);

    $resp->assertStatus(422);
    expect($resp->json('code'))->toBe('binding.store-id-not-allowed');
});

test('store_id 不属于本租户 → 422 binding.store-not-in-tenant', function () {
    ['tenant' => $t] = asUserWithAssignRole();
    $target = User::factory()->create();
    Membership::factory()->create(['user_id' => $target->id, 'tenant_id' => $t->id]);
    $other = Tenant::factory()->create();
    $alienStore = Store::factory()->create(['tenant_id' => $other->id]);
    $r = Role::factory()->create(['tenant_id' => $t->id, 'scope' => 'store']);

    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->postJson("/api/users/{$target->id}/role-bindings", [
            'role_id' => $r->id, 'store_id' => $alienStore->id,
        ]);

    $resp->assertStatus(422);
    expect($resp->json('code'))->toBe('binding.store-not-in-tenant');
});

test('全局模板（tenant_id NULL）可以绑定到本租户员工', function () {
    ['tenant' => $t] = asUserWithAssignRole();
    $target = User::factory()->create();
    Membership::factory()->create(['user_id' => $target->id, 'tenant_id' => $t->id]);
    $tplt = Role::query()->where('code', 'TenantAdmin')->whereNull('tenant_id')->firstOrFail();

    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->postJson("/api/users/{$target->id}/role-bindings", ['role_id' => $tplt->id]);

    $resp->assertStatus(201);
});

test('重复绑定相同组合 → 200 幂等返回已存在', function () {
    ['tenant' => $t] = asUserWithAssignRole();
    $target = User::factory()->create();
    Membership::factory()->create(['user_id' => $target->id, 'tenant_id' => $t->id]);
    $r = Role::factory()->create(['tenant_id' => $t->id, 'scope' => 'tenant']);
    $existing = UserRoleBinding::factory()->create([
        'user_id' => $target->id, 'role_id' => $r->id, 'tenant_id' => $t->id,
    ]);

    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->postJson("/api/users/{$target->id}/role-bindings", ['role_id' => $r->id]);

    $resp->assertStatus(200);
    expect($resp->json('binding.id'))->toBe($existing->id);
});

test('revoked binding 重新绑定 → 复活为 active', function () {
    ['tenant' => $t] = asUserWithAssignRole();
    $target = User::factory()->create();
    Membership::factory()->create(['user_id' => $target->id, 'tenant_id' => $t->id]);
    $r = Role::factory()->create(['tenant_id' => $t->id, 'scope' => 'tenant']);
    $revoked = UserRoleBinding::factory()->revoked()->create([
        'user_id' => $target->id, 'role_id' => $r->id, 'tenant_id' => $t->id,
    ]);

    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->postJson("/api/users/{$target->id}/role-bindings", ['role_id' => $r->id]);

    $resp->assertStatus(200);
    expect($revoked->fresh()->status)->toBe('active');
});

test('DELETE /api/role-bindings/{id} 撤销改 status=revoked，留痕', function () {
    ['tenant' => $t] = asUserWithAssignRole();
    $target = User::factory()->create();
    Membership::factory()->create(['user_id' => $target->id, 'tenant_id' => $t->id]);
    $r = Role::factory()->create(['tenant_id' => $t->id, 'scope' => 'tenant']);
    $b = UserRoleBinding::factory()->create([
        'user_id' => $target->id, 'role_id' => $r->id, 'tenant_id' => $t->id,
    ]);

    $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->deleteJson("/api/role-bindings/{$b->id}")->assertStatus(204);

    expect($b->fresh()->status)->toBe('revoked');  // 不删行
});

test('DELETE 别租户的 binding → 404', function () {
    ['tenant' => $t] = asUserWithAssignRole();
    $other = Tenant::factory()->create();
    $b = UserRoleBinding::factory()->create(['tenant_id' => $other->id]);
    $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->deleteJson("/api/role-bindings/{$b->id}")->assertStatus(404);
});
