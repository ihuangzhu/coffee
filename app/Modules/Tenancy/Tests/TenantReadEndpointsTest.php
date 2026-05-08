<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\Membership;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

afterEach(function () {
    app(CurrentTenant::class)->set(null);
});

test('GET /api/tenants/current 返回 X-Tenant-Id 对应租户', function () {
    $u = User::factory()->create();
    $t = Tenant::factory()->create(['name' => '示范咖啡']);
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $t->id]);
    Sanctum::actingAs($u);

    $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->getJson('/api/tenants/current')
        ->assertOk()
        ->assertJson(['id' => $t->id, 'name' => '示范咖啡']);
});

test('GET /api/stores 仅返回当前租户 stores（跨租户隔离）', function () {
    $u = User::factory()->create();
    $tA = Tenant::factory()->create();
    $tB = Tenant::factory()->create();
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $tA->id]);

    Store::factory()->create(['tenant_id' => $tA->id, 'name' => 'A1 店']);
    Store::factory()->create(['tenant_id' => $tA->id, 'name' => 'A2 店']);
    Store::factory()->create(['tenant_id' => $tB->id, 'name' => 'B1 店']);

    Sanctum::actingAs($u);
    $resp = $this->withHeaders(['X-Tenant-Id' => $tA->id])->getJson('/api/stores');
    $resp->assertOk();
    expect($resp->json('stores'))->toHaveCount(2);
    $names = collect($resp->json('stores'))->pluck('name')->sort()->values()->all();
    expect($names)->toBe(['A1 店', 'A2 店']);
});

test('普通 user 用未绑定租户的 X-Tenant-Id 调 /api/stores 收 403', function () {
    $u = User::factory()->create();
    $tA = Tenant::factory()->create();
    $tB = Tenant::factory()->create();
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $tA->id]);
    Store::factory()->create(['tenant_id' => $tB->id]);

    Sanctum::actingAs($u);
    $this->withHeaders(['X-Tenant-Id' => $tB->id])
        ->getJson('/api/stores')
        ->assertStatus(403);
});

test('platform admin 任意 X-Tenant-Id 都能列出该租户 stores', function () {
    $u = User::factory()->platformAdmin()->create();
    $tB = Tenant::factory()->create();
    Store::factory()->create(['tenant_id' => $tB->id, 'name' => 'B1 店']);

    Sanctum::actingAs($u);
    $resp = $this->withHeaders(['X-Tenant-Id' => $tB->id])->getJson('/api/stores');
    $resp->assertOk();
    expect($resp->json('stores'))->toHaveCount(1);
    expect($resp->json('stores.0.name'))->toBe('B1 店');
});
