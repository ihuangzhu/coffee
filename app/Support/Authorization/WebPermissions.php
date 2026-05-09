<?php

declare(strict_types=1);

namespace App\Support\Authorization;

use App\Modules\Authorization\Enums\Permission;
use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\Models\UserRoleBinding;
use App\Modules\Identity\Models\User;

/**
 * Web/Inertia 上下文里"当前用户在当前租户下的权限并集"。
 *
 * - 平台员工（impersonation）：直接给全部 Permission 枚举值
 * - 普通用户：当前租户下所有 active UserRoleBinding 关联的 Role.permissions 合并去重
 *   - 含 tenant 级 + 该用户在当前租户下任意 store 级 binding
 *   - 系统全局角色（tenant_id=null, is_system=true）通过 role_id 也会被纳入
 *
 * 与 BelongsToTenant 全局作用域无关，所有查询都 withoutGlobalScopes()。
 */
class WebPermissions
{
    /**
     * @return array<int, string>
     */
    public static function resolve(?User $user, ?string $tenantId): array
    {
        if (! $user) {
            return [];
        }

        if ($user->is_platform_admin) {
            return array_map(fn (Permission $p) => $p->value, Permission::cases());
        }

        if (! $tenantId) {
            return [];
        }

        $roleIds = UserRoleBinding::query()->withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->pluck('role_id')
            ->unique()
            ->all();

        if (empty($roleIds)) {
            return [];
        }

        $perms = Role::query()->whereIn('id', $roleIds)
            ->pluck('permissions')
            ->flatMap(fn ($p) => is_array($p) ? $p : [])
            ->unique()
            ->values()
            ->all();

        return $perms;
    }
}
