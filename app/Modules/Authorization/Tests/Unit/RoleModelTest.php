<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Role 工厂可创建 tenant 级角色', function () {
    $t = Tenant::factory()->create();
    $r = Role::factory()->create([
        'tenant_id' => $t->id,
        'scope' => 'tenant',
        'permissions' => ['roles.read', 'users.read'],
    ]);

    expect($r->id)->toBeString()->toHaveLength(26);
    expect($r->permissions)->toBe(['roles.read', 'users.read']);
    expect($r->scope)->toBe('tenant');
    expect($r->is_system)->toBeFalse();
});

test('Role 工厂可创建全局模板（tenant_id NULL）', function () {
    $r = Role::factory()->systemTemplate()->create([
        'code' => 'TenantAdmin',
        'permissions' => ['roles.manage'],
    ]);

    expect($r->tenant_id)->toBeNull();
    expect($r->is_system)->toBeTrue();
});

test('permissions JSON cast 工作', function () {
    $r = Role::factory()->create(['permissions' => ['roles.read']]);
    $reloaded = Role::find($r->id);
    expect($reloaded->permissions)->toBe(['roles.read']);
});
