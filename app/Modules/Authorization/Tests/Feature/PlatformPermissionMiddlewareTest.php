<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\PlatformRole;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('platform admin + 含目标平台权限 → 200', function () {
    $u = User::factory()->platformAdmin()->create();
    $pr = PlatformRole::factory()->create(['permissions' => ['platform.tenants.manage']]);
    $u->update(['platform_role_id' => $pr->id]);

    Sanctum::actingAs($u);
    $this->getJson('/api/__platform-perm-probe')->assertOk();
});

test('platform admin 但缺目标权限 → 403', function () {
    $u = User::factory()->platformAdmin()->create();
    $pr = PlatformRole::factory()->create(['permissions' => ['platform.users.manage']]);
    $u->update(['platform_role_id' => $pr->id]);

    Sanctum::actingAs($u);
    $resp = $this->getJson('/api/__platform-perm-probe');
    $resp->assertStatus(403);
    expect($resp->json('code'))->toBe('platform.permission.missing');
});

test('platform admin 但 platform_role_id NULL → 403', function () {
    $u = User::factory()->platformAdmin()->create();
    Sanctum::actingAs($u);
    $this->getJson('/api/__platform-perm-probe')->assertStatus(403);
});

test('非 platform admin → 401/403（被 platform_admin 中间件拦）', function () {
    $u = User::factory()->create();
    Sanctum::actingAs($u);
    $resp = $this->getJson('/api/__platform-perm-probe');
    expect($resp->status())->toBeIn([401, 403]);
});
