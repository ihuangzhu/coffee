<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\Membership;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('非 platform admin 调 platform endpoint 返回 403', function () {
    $u = User::factory()->create();
    Sanctum::actingAs($u);

    $this->postJson('/api/platform/tenants', ['name' => '尝试'])
        ->assertStatus(403);
});

test('未登录调 platform endpoint 返回 401', function () {
    $this->postJson('/api/platform/tenants', ['name' => '尝试'])
        ->assertStatus(401);
});

test('platform admin 创建租户', function () {
    $admin = User::factory()->platformAdmin()->create();
    Sanctum::actingAs($admin);

    $resp = $this->postJson('/api/platform/tenants', ['name' => '九号咖啡'])
        ->assertStatus(201)
        ->assertJsonStructure(['id', 'name', 'status']);

    expect(Tenant::query()->where('name', '九号咖啡')->exists())->toBeTrue();
});

test('platform admin 创建 store', function () {
    $admin = User::factory()->platformAdmin()->create();
    $t = Tenant::factory()->create();
    Sanctum::actingAs($admin);

    $resp = $this->postJson("/api/platform/tenants/{$t->id}/stores", ['name' => '示范店']);
    $resp->assertStatus(201);

    expect(Store::query()->withoutGlobalScopes()->where('name', '示范店')->where('tenant_id', $t->id)->exists())
        ->toBeTrue();
});

test('platform admin 创建 user 并绑定 tenant 级 membership', function () {
    $admin = User::factory()->platformAdmin()->create();
    $t = Tenant::factory()->create();
    Sanctum::actingAs($admin);

    $resp = $this->postJson("/api/platform/tenants/{$t->id}/users", [
        'name' => '李店员',
        'phone' => '13800001111',
        'password' => 'secret123',
    ]);
    $resp->assertStatus(201);

    $newUser = User::where('phone', '13800001111')->firstOrFail();
    $rel = Membership::query()->withoutGlobalScopes()->where('user_id', $newUser->id)->first();
    expect($rel)->not->toBeNull();
    expect($rel->tenant_id)->toBe($t->id);
    expect($rel->store_id)->toBeNull();
    expect($rel->status)->toBe('active');
});

test('platform admin 创建 user 并绑定 store 级 membership', function () {
    $admin = User::factory()->platformAdmin()->create();
    $t = Tenant::factory()->create();
    $s = Store::factory()->create(['tenant_id' => $t->id]);
    Sanctum::actingAs($admin);

    $this->postJson("/api/platform/tenants/{$t->id}/users", [
        'name' => '张店长',
        'phone' => '13800002222',
        'password' => 'secret123',
        'store_id' => $s->id,
    ])->assertStatus(201);

    $rel = Membership::query()->withoutGlobalScopes()
        ->where('tenant_id', $t->id)
        ->whereNotNull('store_id')
        ->first();
    expect($rel->store_id)->toBe($s->id);
});

test('phone 重复创建 user 返回 422', function () {
    $admin = User::factory()->platformAdmin()->create();
    $t = Tenant::factory()->create();
    User::factory()->create(['phone' => '13899998888']);
    Sanctum::actingAs($admin);

    $this->postJson("/api/platform/tenants/{$t->id}/users", [
        'name' => 'X',
        'phone' => '13899998888',
        'password' => 'secret123',
    ])->assertStatus(422)->assertJsonValidationErrors(['phone']);
});
