<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\PlatformRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('PlatformRole 工厂可创建', function () {
    $r = PlatformRole::factory()->create([
        'code' => 'PlatformOps',
        'permissions' => ['platform.tenants.manage'],
    ]);

    expect($r->id)->toBeString()->toHaveLength(26);
    expect($r->permissions)->toBe(['platform.tenants.manage']);
    expect($r->is_system)->toBeFalse();
});

test('code UNIQUE 约束生效', function () {
    PlatformRole::factory()->create(['code' => 'DuplicateCode']);
    expect(fn () => PlatformRole::factory()->create(['code' => 'DuplicateCode']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
