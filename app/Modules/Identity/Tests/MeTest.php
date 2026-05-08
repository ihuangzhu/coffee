<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\Membership;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('GET /api/me 未认证 401', function () {
    $this->getJson('/api/me')->assertStatus(401);
});

test('GET /api/me 返回主体', function () {
    $u = User::factory()->create();
    Sanctum::actingAs($u);

    $this->getJson('/api/me')
        ->assertOk()
        ->assertJson([
            'id' => $u->id,
            'phone' => $u->phone,
            'is_platform_admin' => false,
        ]);
});

test('GET /api/me/memberships 列出所有 active rels（跨租户）', function () {
    $u = User::factory()->create();
    $tA = Tenant::factory()->create(['name' => 'A咖啡']);
    $tB = Tenant::factory()->create(['name' => 'B咖啡']);
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $tA->id]);
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $tB->id]);
    Membership::factory()->left()->create(['user_id' => $u->id, 'tenant_id' => $tA->id]);

    Sanctum::actingAs($u);

    $resp = $this->getJson('/api/me/memberships')->assertOk();
    expect($resp->json('memberships'))->toHaveCount(2);
    $names = collect($resp->json('memberships'))->pluck('tenant_name')->sort()->values()->all();
    expect($names)->toBe(['A咖啡', 'B咖啡']);
});

test('GET /api/me/memberships 不返回 status=left 的', function () {
    $u = User::factory()->create();
    Membership::factory()->left()->create(['user_id' => $u->id]);
    Sanctum::actingAs($u);

    expect($this->getJson('/api/me/memberships')->json('memberships'))->toHaveCount(0);
});
