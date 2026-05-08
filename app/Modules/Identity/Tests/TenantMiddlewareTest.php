<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\Membership;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

afterEach(function () {
    app(CurrentTenant::class)->set(null);
});

test('未认证返回 401', function () {
    $this->getJson('/api/__tenant-probe', ['X-Tenant-Id' => 'whatever'])
        ->assertStatus(401);
});

test('缺 X-Tenant-Id header 返回 403', function () {
    $u = User::factory()->create();
    Sanctum::actingAs($u);

    $this->getJson('/api/__tenant-probe')
        ->assertStatus(403)
        ->assertJson(['error' => 'X-Tenant-Id header required']);
});

test('普通 user 在该 tenant 没有 active membership 返回 403', function () {
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    Sanctum::actingAs($u);

    $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->getJson('/api/__tenant-probe')
        ->assertStatus(403)
        ->assertJson(['error' => 'no active membership']);
});

test('普通 user 有 active membership 通过', function () {
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    Membership::factory()->create([
        'user_id' => $u->id, 'tenant_id' => $t->id, 'status' => 'active',
    ]);
    Sanctum::actingAs($u);

    $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->getJson('/api/__tenant-probe')
        ->assertOk()
        ->assertJson([
            'tenant_id' => $t->id,
            'is_platform_impersonation' => false,
        ]);
});

test('platform admin 任意 X-Tenant-Id 通过且标 impersonation', function () {
    $u = User::factory()->platformAdmin()->create();
    $t = Tenant::factory()->create();
    Sanctum::actingAs($u);

    $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->getJson('/api/__tenant-probe')
        ->assertOk()
        ->assertJson([
            'tenant_id' => $t->id,
            'is_platform_impersonation' => true,
        ]);
});

test('membership.status=left 返回 403', function () {
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    Membership::factory()->left()->create([
        'user_id' => $u->id, 'tenant_id' => $t->id,
    ]);
    Sanctum::actingAs($u);

    $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->getJson('/api/__tenant-probe')
        ->assertStatus(403);
});

test('普通员工通过后注入 effective_permissions 取并集', function () {
    $u = \App\Modules\Identity\Models\User::factory()->create();
    $t = \App\Modules\Tenancy\Models\Tenant::factory()->create();
    \App\Modules\Identity\Models\Membership::factory()->create([
        'user_id' => $u->id, 'tenant_id' => $t->id, 'status' => 'active',
    ]);
    $r1 = \App\Modules\Authorization\Models\Role::factory()->create([
        'tenant_id' => $t->id, 'permissions' => ['roles.read', 'users.read'],
    ]);
    $r2 = \App\Modules\Authorization\Models\Role::factory()->create([
        'tenant_id' => $t->id, 'permissions' => ['users.read', 'stores.read'],
    ]);
    \App\Modules\Authorization\Models\UserRoleBinding::factory()->create([
        'user_id' => $u->id, 'role_id' => $r1->id, 'tenant_id' => $t->id,
    ]);
    \App\Modules\Authorization\Models\UserRoleBinding::factory()->create([
        'user_id' => $u->id, 'role_id' => $r2->id, 'tenant_id' => $t->id,
    ]);

    \Laravel\Sanctum\Sanctum::actingAs($u);
    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/__tenant-probe');

    $resp->assertOk();
    expect($resp->json('effective_permissions'))->toEqualCanonicalizing(['roles.read', 'users.read', 'stores.read']);
    expect($resp->json('role_bindings_count'))->toBe(2);
    expect($resp->json('is_platform_impersonation'))->toBeFalse();
});

test('platform admin impersonation 注入 platform_role', function () {
    $admin = \App\Modules\Identity\Models\User::factory()->platformAdmin()->create();
    $pr = \App\Modules\Authorization\Models\PlatformRole::factory()->create([
        'code' => 'TestSuperAdmin',
        'permissions' => ['platform.impersonate.full'],
    ]);
    $admin->update(['platform_role_id' => $pr->id]);
    $t = \App\Modules\Tenancy\Models\Tenant::factory()->create();

    \Laravel\Sanctum\Sanctum::actingAs($admin);
    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/__tenant-probe');

    $resp->assertOk();
    expect($resp->json('is_platform_impersonation'))->toBeTrue();
    expect($resp->json('platform_role_code'))->toBe('TestSuperAdmin');
    expect($resp->json('effective_permissions'))->toBeNull();
});
