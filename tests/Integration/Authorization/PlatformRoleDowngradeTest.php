<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\PlatformRole;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('PlatformSuperAdmin 改成 PlatformReadOnly 后无法 POST', function () {
    $u = User::factory()->platformAdmin()->create();
    $superRole = PlatformRole::query()->where('code', 'PlatformSuperAdmin')->firstOrFail();
    $readonlyRole = PlatformRole::query()->where('code', 'PlatformReadOnly')->firstOrFail();
    $u->update(['platform_role_id' => $superRole->id]);

    Sanctum::actingAs($u);

    // Super 状态：可创建 tenant
    $this->postJson('/api/platform/tenants', ['name' => '九号'])->assertStatus(201);

    // 降级（unsetRelation 清除 Eloquent 关系缓存，确保中间件读取新角色）
    $u->update(['platform_role_id' => $readonlyRole->id]);
    $u->unsetRelation('platformRole');

    // ReadOnly 状态：不能 POST
    $resp = $this->postJson('/api/platform/tenants', ['name' => '示范']);
    $resp->assertStatus(403);
    expect($resp->json('code'))->toBe('platform.permission.missing');
});
