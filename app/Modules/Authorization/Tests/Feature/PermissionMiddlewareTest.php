<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\PlatformRole;
use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\Models\UserRoleBinding;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

afterEach(fn () => app(CurrentTenant::class)->set(null));

function prep_member(array $perms, string $scope = 'tenant'): array
{
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $t->id]);
    $r = Role::factory()->create(['tenant_id' => $t->id, 'permissions' => $perms, 'scope' => $scope]);
    UserRoleBinding::factory()->create(['user_id' => $u->id, 'role_id' => $r->id, 'tenant_id' => $t->id]);
    return ['user' => $u, 'tenant' => $t];
}

function prep_platform_admin(array $perms): array
{
    $u = User::factory()->platformAdmin()->create();
    $pr = PlatformRole::factory()->create(['permissions' => $perms]);
    $u->update(['platform_role_id' => $pr->id]);
    $t = Tenant::factory()->create();
    return ['user' => $u, 'tenant' => $t];
}

test('商户员工有目标权限 → 200', function () {
    ['user' => $u, 'tenant' => $t] = prep_member(['roles.manage']);
    Sanctum::actingAs($u);
    $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/__perm-probe')->assertOk();
});

test('商户员工缺目标权限 → 403 permission.missing', function () {
    ['user' => $u, 'tenant' => $t] = prep_member(['roles.read']);
    Sanctum::actingAs($u);
    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/__perm-probe');
    $resp->assertStatus(403);
    expect($resp->json('code'))->toBe('permission.missing');
});

test('多 binding 取并集后含目标权限 → 200', function () {
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $t->id]);
    $r1 = Role::factory()->create(['tenant_id' => $t->id, 'permissions' => ['roles.read']]);
    $r2 = Role::factory()->create(['tenant_id' => $t->id, 'permissions' => ['roles.manage']]);
    UserRoleBinding::factory()->create(['user_id' => $u->id, 'role_id' => $r1->id, 'tenant_id' => $t->id]);
    UserRoleBinding::factory()->create(['user_id' => $u->id, 'role_id' => $r2->id, 'tenant_id' => $t->id]);

    Sanctum::actingAs($u);
    $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/__perm-probe')->assertOk();
});

test('revoked binding 不计入', function () {
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $t->id]);
    $r = Role::factory()->create(['tenant_id' => $t->id, 'permissions' => ['roles.manage']]);
    UserRoleBinding::factory()->revoked()->create(['user_id' => $u->id, 'role_id' => $r->id, 'tenant_id' => $t->id]);

    Sanctum::actingAs($u);
    $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/__perm-probe')->assertStatus(403);
});

test('platform admin + ImpersonateFull → 任意方法 200', function () {
    ['user' => $u, 'tenant' => $t] = prep_platform_admin(['platform.impersonate.full']);
    Sanctum::actingAs($u);
    $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/__perm-probe')->assertOk();
    $this->withHeaders(['X-Tenant-Id' => $t->id])->postJson('/api/__perm-probe-write')->assertOk();
});

test('platform admin + ImpersonateReadOnly + GET → 200', function () {
    ['user' => $u, 'tenant' => $t] = prep_platform_admin(['platform.impersonate.read-only']);
    Sanctum::actingAs($u);
    $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/__perm-probe')->assertOk();
});

test('platform admin + ImpersonateReadOnly + POST → 403 method-not-allowed', function () {
    ['user' => $u, 'tenant' => $t] = prep_platform_admin(['platform.impersonate.read-only']);
    Sanctum::actingAs($u);
    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])->postJson('/api/__perm-probe-write');
    $resp->assertStatus(403);
    expect($resp->json('code'))->toBe('platform.impersonation.method-not-allowed');
});

test('platform admin 但无 platform_role → 403', function () {
    $u = User::factory()->platformAdmin()->create();
    $t = Tenant::factory()->create();
    Sanctum::actingAs($u);
    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/__perm-probe');
    $resp->assertStatus(403);
    expect($resp->json('code'))->toBe('platform.impersonation.no-role');
});
