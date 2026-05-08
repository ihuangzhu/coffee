<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\PlatformRole;
use App\Modules\Authorization\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('migrate 后 roles 有 3 个全局模板（is_system=true）', function () {
    $roles = Role::query()->where('is_system', true)->whereNull('tenant_id')->get();
    $codes = $roles->pluck('code')->sort()->values()->all();
    expect($codes)->toBe(['StoreClerk', 'StoreManager', 'TenantAdmin']);
});

test('TenantAdmin 含全部 6 个商户域权限', function () {
    $r = Role::query()->where('code', 'TenantAdmin')->whereNull('tenant_id')->firstOrFail();
    expect($r->permissions)->toEqualCanonicalizing([
        'roles.read', 'roles.manage', 'users.read', 'users.assign-role', 'tenant.read', 'stores.read',
    ]);
    expect($r->scope)->toBe('tenant');
});

test('StoreManager 含读类权限，scope=store', function () {
    $r = Role::query()->where('code', 'StoreManager')->whereNull('tenant_id')->firstOrFail();
    expect($r->permissions)->toEqualCanonicalizing(['roles.read', 'users.read', 'tenant.read', 'stores.read']);
    expect($r->scope)->toBe('store');
});

test('platform_roles 有 3 个 is_system 角色', function () {
    $roles = PlatformRole::query()->where('is_system', true)->get();
    $codes = $roles->pluck('code')->sort()->values()->all();
    expect($codes)->toBe(['PlatformOps', 'PlatformReadOnly', 'PlatformSuperAdmin']);
});

test('PlatformSuperAdmin 含全部 6 个平台权限', function () {
    $r = PlatformRole::query()->where('code', 'PlatformSuperAdmin')->firstOrFail();
    expect($r->permissions)->toEqualCanonicalizing([
        'platform.impersonate.full', 'platform.impersonate.read-only',
        'platform.tenants.manage', 'platform.stores.manage',
        'platform.users.manage', 'platform.roles.manage',
    ]);
});

test('PlatformReadOnly 仅含 ImpersonateReadOnly', function () {
    $r = PlatformRole::query()->where('code', 'PlatformReadOnly')->firstOrFail();
    expect($r->permissions)->toBe(['platform.impersonate.read-only']);
});
