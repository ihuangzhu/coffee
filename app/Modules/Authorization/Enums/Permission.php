<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Enums;

/**
 * 商户域权限（INV-A：所有 case 不可有 platform. 前缀）。
 *
 * 单一来源：写入 roles.permissions 时仅接受这里枚举值的 string。
 * 后续 Goods / Order / Inventory 等模块各自 PR 在此追加 case。
 */
enum Permission: string
{
    case RolesRead = 'roles.read';
    case RolesManage = 'roles.manage';
    case UsersRead = 'users.read';
    case UsersAssignRole = 'users.assign-role';
    case TenantRead = 'tenant.read';
    case StoresRead = 'stores.read';
}
