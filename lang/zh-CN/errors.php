<?php

declare(strict_types=1);

return [
    // RBAC
    'permission.missing' => '缺少权限：:permission_key',
    'platform.permission.missing' => '缺少平台权限：:permission_key',
    'platform.impersonation.method-not-allowed' => '只读伪装态不允许该方法：:method',
    'platform.impersonation.no-role' => '平台员工尚未分配 platform_role',
    'permission.cross-domain' => '权限码跨域不允许',
    'role.in-use' => '角色被引用，无法删除（绑定数：:binding_count）',
    'role.system-locked' => '系统内置角色不可修改/删除',
    'binding.store-not-in-tenant' => 'store_id 不属于当前租户',
    'binding.store-id-required' => '门店级角色绑定必须传 store_id',
    'binding.store-id-not-allowed' => '租户级角色绑定不可传 store_id',
    'platform-role.target-not-admin' => '目标用户不是平台员工',
];
