<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\PlatformRole;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function asPlatformAdminWith(string $perm): User
{
    $u = User::factory()->platformAdmin()->create();
    $pr = PlatformRole::factory()->create(['permissions' => [$perm]]);
    $u->update(['platform_role_id' => $pr->id]);
    Sanctum::actingAs($u);

    return $u;
}

test('GET /api/platform/roles 列出所有平台角色', function () {
    asPlatformAdminWith('platform.roles.manage');
    $resp = $this->getJson('/api/platform/roles');
    $resp->assertOk();
    expect(count($resp->json('roles')))->toBeGreaterThanOrEqual(3);  // 3 个 is_system 已 seed
});

test('POST /api/platform/roles 创建平台角色', function () {
    asPlatformAdminWith('platform.roles.manage');
    $resp = $this->postJson('/api/platform/roles', [
        'name' => '客服', 'code' => 'PlatformSupport',
        'permissions' => ['platform.impersonate.read-only'],
    ]);
    $resp->assertStatus(201);
    expect($resp->json('role.is_system'))->toBeFalse();
});

test('POST /api/platform/roles 含商户域权限 → 422', function () {
    asPlatformAdminWith('platform.roles.manage');
    $resp = $this->postJson('/api/platform/roles', [
        'name' => 'X', 'code' => 'X', 'permissions' => ['roles.manage'],
    ]);
    $resp->assertStatus(422);
});

test('PATCH /api/platform/roles/{id} 改自建角色', function () {
    asPlatformAdminWith('platform.roles.manage');
    $r = PlatformRole::factory()->create(['name' => '原名', 'code' => 'X']);

    $this->patchJson("/api/platform/roles/{$r->id}", ['name' => '新名'])->assertOk();
    expect($r->fresh()->name)->toBe('新名');
});

test('PATCH is_system → 403 role.system-locked', function () {
    asPlatformAdminWith('platform.roles.manage');
    $r = PlatformRole::query()->where('code', 'PlatformReadOnly')->firstOrFail();

    $resp = $this->patchJson("/api/platform/roles/{$r->id}", ['name' => '篡改']);
    $resp->assertStatus(403);
    expect($resp->json('code'))->toBe('role.system-locked');
});

test('DELETE 自建角色 → 204', function () {
    asPlatformAdminWith('platform.roles.manage');
    $r = PlatformRole::factory()->create(['code' => 'X']);
    $this->deleteJson("/api/platform/roles/{$r->id}")->assertStatus(204);
});

test('DELETE is_system → 403', function () {
    asPlatformAdminWith('platform.roles.manage');
    $r = PlatformRole::query()->where('is_system', true)->first();
    $this->deleteJson("/api/platform/roles/{$r->id}")->assertStatus(403);
});

test('DELETE 被 user 引用 → 409', function () {
    $admin = asPlatformAdminWith('platform.roles.manage');
    $r = PlatformRole::factory()->create();
    $other = User::factory()->platformAdmin()->create(['platform_role_id' => $r->id]);

    $resp = $this->deleteJson("/api/platform/roles/{$r->id}");
    $resp->assertStatus(409);
    expect($resp->json('code'))->toBe('role.in-use');
});

test('缺 platform.roles.manage → 403', function () {
    asPlatformAdminWith('platform.tenants.manage');
    $this->getJson('/api/platform/roles')->assertStatus(403);
});
