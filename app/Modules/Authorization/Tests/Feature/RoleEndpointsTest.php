<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\Models\UserRoleBinding;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function actAsMemberWith(array $perms): array
{
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $t->id]);
    $r = Role::factory()->create(['tenant_id' => $t->id, 'permissions' => $perms]);
    UserRoleBinding::factory()->create(['user_id' => $u->id, 'role_id' => $r->id, 'tenant_id' => $t->id]);
    Sanctum::actingAs($u);
    return ['user' => $u, 'tenant' => $t];
}

test('GET /api/roles 列出本租户角色 + 全局模板', function () {
    ['tenant' => $t] = actAsMemberWith(['roles.read']);

    Role::factory()->create(['tenant_id' => $t->id, 'name' => '收银员', 'code' => 'Cashier']);
    $other = Tenant::factory()->create();
    Role::factory()->create(['tenant_id' => $other->id, 'name' => '别家员工', 'code' => 'Other']);

    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/roles');
    $resp->assertOk();
    $names = collect($resp->json('roles'))->pluck('name')->all();
    expect($names)->toContain('商户管理员', '门店店长', '门店店员', '收银员');
    expect($names)->not->toContain('别家员工');
});

test('GET /api/roles 缺权限 → 403', function () {
    ['tenant' => $t] = actAsMemberWith([]);
    $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/roles')->assertStatus(403);
});

test('POST /api/roles 创建租户专属角色', function () {
    ['tenant' => $t] = actAsMemberWith(['roles.manage']);

    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])->postJson('/api/roles', [
        'name' => '收银员',
        'code' => 'Cashier',
        'scope' => 'store',
        'permissions' => ['stores.read', 'tenant.read'],
    ]);

    $resp->assertStatus(201);
    expect($resp->json('role.tenant_id'))->toBe($t->id);
    expect($resp->json('role.is_system'))->toBeFalse();
});

test('POST /api/roles 含 platform.* → 422 permission.cross-domain', function () {
    ['tenant' => $t] = actAsMemberWith(['roles.manage']);

    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])->postJson('/api/roles', [
        'name' => '搞事',
        'code' => 'Bad',
        'scope' => 'tenant',
        'permissions' => ['platform.tenants.manage'],
    ]);

    $resp->assertStatus(422);
    expect($resp->json('errors.permissions'))->not->toBeEmpty();
});

test('POST /api/roles 缺 roles.manage → 403', function () {
    ['tenant' => $t] = actAsMemberWith(['roles.read']);
    $this->withHeaders(['X-Tenant-Id' => $t->id])->postJson('/api/roles', [
        'name' => 'X', 'code' => 'X', 'scope' => 'tenant', 'permissions' => [],
    ])->assertStatus(403);
});
