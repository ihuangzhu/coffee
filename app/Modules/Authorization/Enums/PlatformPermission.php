<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Enums;

/**
 * 平台域权限（INV-A：所有 case 必须 platform. 前缀）。
 *
 * 与 Permission 严格不互通（INV-14）：写入 platform_roles.permissions
 * 时仅接受这里枚举值；写入 roles.permissions 时禁止任何 platform.* 前缀。
 *
 * 双 enum 结构强制隔离平台与商户能力空间。
 */
enum PlatformPermission: string
{
    case ImpersonateFull = 'platform.impersonate.full';
    case ImpersonateReadOnly = 'platform.impersonate.read-only';
    case TenantsManage = 'platform.tenants.manage';
    case StoresManage = 'platform.stores.manage';
    case UsersManage = 'platform.users.manage';
    case PlatformRolesManage = 'platform.roles.manage';
}
