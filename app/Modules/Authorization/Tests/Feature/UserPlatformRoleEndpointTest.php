<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\PlatformRole;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('PATCH /api/platform/users/{id}/platform-role 给 platform admin 分配', function () {
    $admin = User::factory()->platformAdmin()->create();
    $callerRole = PlatformRole::factory()->create(['permissions' => ['platform.roles.manage']]);
    $admin->update(['platform_role_id' => $callerRole->id]);
    Sanctum::actingAs($admin);

    $target = User::factory()->platformAdmin()->create();
    $newRole = PlatformRole::factory()->create();

    $this->patchJson("/api/platform/users/{$target->id}/platform-role", [
        'platform_role_id' => $newRole->id,
    ])->assertOk();

    expect($target->fresh()->platform_role_id)->toBe($newRole->id);
});

test('给非 platform admin 设 → 422 platform-role.target-not-admin', function () {
    $admin = User::factory()->platformAdmin()->create();
    $callerRole = PlatformRole::factory()->create(['permissions' => ['platform.roles.manage']]);
    $admin->update(['platform_role_id' => $callerRole->id]);
    Sanctum::actingAs($admin);

    $target = User::factory()->create();  // is_platform_admin=false
    $newRole = PlatformRole::factory()->create();

    $resp = $this->patchJson("/api/platform/users/{$target->id}/platform-role", [
        'platform_role_id' => $newRole->id,
    ]);
    $resp->assertStatus(422);
    expect($resp->json('code'))->toBe('platform-role.target-not-admin');
});

test('解绑（platform_role_id=null）', function () {
    $admin = User::factory()->platformAdmin()->create();
    $callerRole = PlatformRole::factory()->create(['permissions' => ['platform.roles.manage']]);
    $admin->update(['platform_role_id' => $callerRole->id]);
    Sanctum::actingAs($admin);

    $target = User::factory()->platformAdmin()->create();
    $oldRole = PlatformRole::factory()->create();
    $target->update(['platform_role_id' => $oldRole->id]);

    $this->patchJson("/api/platform/users/{$target->id}/platform-role", [
        'platform_role_id' => null,
    ])->assertOk();

    expect($target->fresh()->platform_role_id)->toBeNull();
});
