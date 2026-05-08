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

function asUserWithUsersRead(): array
{
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $t->id]);
    $r = Role::factory()->create(['tenant_id' => $t->id, 'permissions' => ['users.read']]);
    UserRoleBinding::factory()->create(['user_id' => $u->id, 'role_id' => $r->id, 'tenant_id' => $t->id]);
    Sanctum::actingAs($u);
    return ['user' => $u, 'tenant' => $t];
}

test('GET /api/users 列出本租户 active 员工', function () {
    ['user' => $self, 'tenant' => $t] = asUserWithUsersRead();
    $u2 = User::factory()->create(['name' => '同事 A']);
    Membership::factory()->create(['user_id' => $u2->id, 'tenant_id' => $t->id, 'status' => 'active']);
    $u3 = User::factory()->create(['name' => '已离职']);
    Membership::factory()->create(['user_id' => $u3->id, 'tenant_id' => $t->id, 'status' => 'left']);

    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/users');
    $resp->assertOk();
    $names = collect($resp->json('users'))->pluck('name')->all();
    expect($names)->toContain('同事 A');
    expect($names)->not->toContain('已离职');
});

test('GET /api/users 不含别租户员工', function () {
    ['tenant' => $t] = asUserWithUsersRead();
    $other = Tenant::factory()->create();
    $alien = User::factory()->create(['name' => '外人']);
    Membership::factory()->create(['user_id' => $alien->id, 'tenant_id' => $other->id]);

    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/users');
    $names = collect($resp->json('users'))->pluck('name')->all();
    expect($names)->not->toContain('外人');
});

test('GET /api/users 缺 users.read → 403', function () {
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $t->id]);
    Sanctum::actingAs($u);
    $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/users')->assertStatus(403);
});
