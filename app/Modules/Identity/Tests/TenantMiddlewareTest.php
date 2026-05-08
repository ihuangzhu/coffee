<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserOrgRel;
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
    UserOrgRel::factory()->create([
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
    UserOrgRel::factory()->left()->create([
        'user_id' => $u->id, 'tenant_id' => $t->id,
    ]);
    Sanctum::actingAs($u);

    $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->getJson('/api/__tenant-probe')
        ->assertStatus(403);
});
