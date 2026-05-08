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

test('TenantAdmin 撤回后立即失去权限', function () {
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $t->id]);
    $tplt = Role::query()->where('code', 'TenantAdmin')->whereNull('tenant_id')->firstOrFail();
    $b = UserRoleBinding::factory()->create([
        'user_id' => $u->id, 'role_id' => $tplt->id, 'tenant_id' => $t->id,
    ]);

    Sanctum::actingAs($u);

    // Step 1: 拥有 roles.manage，可创建角色
    $this->withHeaders(['X-Tenant-Id' => $t->id])->postJson('/api/roles', [
        'name' => 'X', 'code' => 'X', 'scope' => 'tenant', 'permissions' => ['tenant.read'],
    ])->assertStatus(201);

    // Step 2: 撤回该 binding
    $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->deleteJson("/api/role-bindings/{$b->id}")->assertStatus(204);

    // Step 3: 同 token 再调 → 403 permission.missing
    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])->postJson('/api/roles', [
        'name' => 'Y', 'code' => 'Y', 'scope' => 'tenant', 'permissions' => ['tenant.read'],
    ]);
    $resp->assertStatus(403);
    expect($resp->json('code'))->toBe('permission.missing');
});
