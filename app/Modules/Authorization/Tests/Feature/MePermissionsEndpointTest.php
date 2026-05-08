<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\PlatformRole;
use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\Models\UserRoleBinding;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('普通员工返回 effective_permissions 扁平列表', function () {
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $t->id]);
    $r = Role::factory()->create(['tenant_id' => $t->id, 'permissions' => ['roles.read', 'users.read']]);
    UserRoleBinding::factory()->create(['user_id' => $u->id, 'role_id' => $r->id, 'tenant_id' => $t->id]);

    Sanctum::actingAs($u);
    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/me/permissions');
    $resp->assertOk()->assertJson(['is_platform_impersonation' => false]);
    expect($resp->json('effective_permissions'))->toEqualCanonicalizing(['roles.read', 'users.read']);
    expect($resp->json('platform_permissions'))->toBeNull();
});

test('平台员工伪装态返回 platform_permissions，effective_permissions 为 null', function () {
    $u = User::factory()->platformAdmin()->create();
    $pr = PlatformRole::factory()->create(['permissions' => ['platform.tenants.manage']]);
    $u->update(['platform_role_id' => $pr->id]);
    $t = Tenant::factory()->create();

    Sanctum::actingAs($u);
    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/me/permissions');
    $resp->assertOk()->assertJson([
        'is_platform_impersonation' => true,
        'effective_permissions' => null,
    ]);
    expect($resp->json('platform_permissions'))->toBe(['platform.tenants.manage']);
});

test('跨租户切 X-Tenant-Id 时 effective_permissions 重新解析', function () {
    $u = User::factory()->create();
    $tA = Tenant::factory()->create();
    $tB = Tenant::factory()->create();
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $tA->id]);
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $tB->id]);
    $rA = Role::factory()->create(['tenant_id' => $tA->id, 'permissions' => ['roles.read']]);
    $rB = Role::factory()->create(['tenant_id' => $tB->id, 'permissions' => ['users.read']]);
    UserRoleBinding::factory()->create(['user_id' => $u->id, 'role_id' => $rA->id, 'tenant_id' => $tA->id]);
    UserRoleBinding::factory()->create(['user_id' => $u->id, 'role_id' => $rB->id, 'tenant_id' => $tB->id]);

    Sanctum::actingAs($u);
    expect($this->withHeaders(['X-Tenant-Id' => $tA->id])->getJson('/api/me/permissions')->json('effective_permissions'))
        ->toBe(['roles.read']);
    expect($this->withHeaders(['X-Tenant-Id' => $tB->id])->getJson('/api/me/permissions')->json('effective_permissions'))
        ->toBe(['users.read']);
});
