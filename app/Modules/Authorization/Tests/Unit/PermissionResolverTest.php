<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\Models\UserRoleBinding;
use App\Modules\Authorization\Services\PermissionResolver;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('单个 binding 的权限正确返回', function () {
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    $r = Role::factory()->create(['tenant_id' => $t->id, 'permissions' => ['roles.read', 'users.read']]);
    UserRoleBinding::factory()->create(['user_id' => $u->id, 'role_id' => $r->id, 'tenant_id' => $t->id]);

    $resolver = new PermissionResolver();
    $perms = $resolver->resolveForUserInTenant($u, $t->id);
    expect($perms)->toEqualCanonicalizing(['roles.read', 'users.read']);
});

test('多个 active binding 的权限取并集去重', function () {
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    $r1 = Role::factory()->create(['tenant_id' => $t->id, 'permissions' => ['roles.read', 'users.read']]);
    $r2 = Role::factory()->create(['tenant_id' => $t->id, 'permissions' => ['users.read', 'stores.read']]);
    UserRoleBinding::factory()->create(['user_id' => $u->id, 'role_id' => $r1->id, 'tenant_id' => $t->id]);
    UserRoleBinding::factory()->create(['user_id' => $u->id, 'role_id' => $r2->id, 'tenant_id' => $t->id]);

    $perms = (new PermissionResolver())->resolveForUserInTenant($u, $t->id);
    expect($perms)->toEqualCanonicalizing(['roles.read', 'users.read', 'stores.read']);
});

test('revoked binding 不计入', function () {
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    $rActive = Role::factory()->create(['tenant_id' => $t->id, 'permissions' => ['roles.read']]);
    $rRevoked = Role::factory()->create(['tenant_id' => $t->id, 'permissions' => ['users.read']]);
    UserRoleBinding::factory()->create(['user_id' => $u->id, 'role_id' => $rActive->id, 'tenant_id' => $t->id]);
    UserRoleBinding::factory()->revoked()->create(['user_id' => $u->id, 'role_id' => $rRevoked->id, 'tenant_id' => $t->id]);

    $perms = (new PermissionResolver())->resolveForUserInTenant($u, $t->id);
    expect($perms)->toBe(['roles.read']);
});

test('其它租户的 binding 不计入', function () {
    $u = User::factory()->create();
    $tA = Tenant::factory()->create();
    $tB = Tenant::factory()->create();
    $rA = Role::factory()->create(['tenant_id' => $tA->id, 'permissions' => ['roles.read']]);
    $rB = Role::factory()->create(['tenant_id' => $tB->id, 'permissions' => ['users.read']]);
    UserRoleBinding::factory()->create(['user_id' => $u->id, 'role_id' => $rA->id, 'tenant_id' => $tA->id]);
    UserRoleBinding::factory()->create(['user_id' => $u->id, 'role_id' => $rB->id, 'tenant_id' => $tB->id]);

    expect((new PermissionResolver())->resolveForUserInTenant($u, $tA->id))->toBe(['roles.read']);
    expect((new PermissionResolver())->resolveForUserInTenant($u, $tB->id))->toBe(['users.read']);
});

test('user 在该租户无任何 active binding 返回空数组', function () {
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    expect((new PermissionResolver())->resolveForUserInTenant($u, $t->id))->toBe([]);
});

test('resolveBindingsForUserInTenant 返回 active 绑定集合（不计 revoked）', function () {
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    UserRoleBinding::factory()->count(2)->create(['user_id' => $u->id, 'tenant_id' => $t->id]);
    UserRoleBinding::factory()->revoked()->create(['user_id' => $u->id, 'tenant_id' => $t->id]);

    expect((new PermissionResolver())->resolveBindingsForUserInTenant($u, $t->id))->toHaveCount(2);
});

test('permissionsFromBindings 直接从 collection 取并集（无 DB query）', function () {
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    $r = Role::factory()->create(['tenant_id' => $t->id, 'permissions' => ['roles.read']]);
    UserRoleBinding::factory()->create(['user_id' => $u->id, 'role_id' => $r->id, 'tenant_id' => $t->id]);

    $resolver = new PermissionResolver();
    $bindings = $resolver->resolveBindingsForUserInTenant($u, $t->id);

    expect($resolver->permissionsFromBindings($bindings))->toBe(['roles.read']);
    expect($resolver->permissionsFromBindings(collect()))->toBe([]);
});
