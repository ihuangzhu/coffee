<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\PlatformRole;
use App\Modules\Authorization\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $merchantPerms = [
            'roles.read', 'roles.manage', 'users.read', 'users.assign-role', 'tenant.read', 'stores.read',
        ];

        // 商户全局模板（tenant_id=NULL）
        Role::query()->create([
            'tenant_id' => null,
            'name' => '商户管理员',
            'code' => 'TenantAdmin',
            'scope' => 'tenant',
            'permissions' => $merchantPerms,
            'is_system' => true,
        ]);
        Role::query()->create([
            'tenant_id' => null,
            'name' => '门店店长',
            'code' => 'StoreManager',
            'scope' => 'store',
            'permissions' => ['roles.read', 'users.read', 'tenant.read', 'stores.read'],
            'is_system' => true,
        ]);
        Role::query()->create([
            'tenant_id' => null,
            'name' => '门店店员',
            'code' => 'StoreClerk',
            'scope' => 'store',
            'permissions' => ['tenant.read', 'stores.read'],
            'is_system' => true,
        ]);

        // 平台角色
        $platformPerms = [
            'platform.impersonate.full', 'platform.impersonate.read-only',
            'platform.tenants.manage', 'platform.stores.manage',
            'platform.users.manage', 'platform.roles.manage',
        ];
        PlatformRole::query()->create([
            'name' => '平台超级管理员',
            'code' => 'PlatformSuperAdmin',
            'permissions' => $platformPerms,
            'is_system' => true,
        ]);
        PlatformRole::query()->create([
            'name' => '平台运营',
            'code' => 'PlatformOps',
            'permissions' => [
                'platform.impersonate.full', 'platform.tenants.manage',
                'platform.stores.manage', 'platform.users.manage',
            ],
            'is_system' => true,
        ]);
        PlatformRole::query()->create([
            'name' => '平台只读',
            'code' => 'PlatformReadOnly',
            'permissions' => ['platform.impersonate.read-only'],
            'is_system' => true,
        ]);
    }

    public function down(): void
    {
        Role::query()->where('is_system', true)->whereNull('tenant_id')
            ->whereIn('code', ['TenantAdmin', 'StoreManager', 'StoreClerk'])
            ->delete();
        PlatformRole::query()->where('is_system', true)
            ->whereIn('code', ['PlatformSuperAdmin', 'PlatformOps', 'PlatformReadOnly'])
            ->delete();
    }
};
