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
