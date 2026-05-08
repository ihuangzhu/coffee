<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\PlatformRole;
use App\Modules\Identity\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('PlatformRole 工厂可创建', function () {
    $r = PlatformRole::factory()->create([
        'code' => 'CustomOps',
        'permissions' => ['platform.tenants.manage'],
    ]);

    expect($r->id)->toBeString()->toHaveLength(26);
    expect($r->permissions)->toBe(['platform.tenants.manage']);
    expect($r->is_system)->toBeFalse();
});

test('code UNIQUE 约束生效', function () {
    PlatformRole::factory()->create(['code' => 'DuplicateCode']);
    expect(fn () => PlatformRole::factory()->create(['code' => 'DuplicateCode']))
        ->toThrow(QueryException::class);
});

test('User.platformRole 关系返回正确 PlatformRole', function () {
    $role = PlatformRole::factory()->create();
    $user = User::factory()->platformAdmin()->create([
        'platform_role_id' => $role->id,
    ]);
    expect($user->platformRole->id)->toBe($role->id);
});

test('User.platformRole 默认 null', function () {
    $user = User::factory()->create();
    expect($user->platformRole)->toBeNull();
});
