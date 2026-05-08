<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Services;

use App\Modules\Authorization\Models\UserRoleBinding;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Collection;

/**
 * 权限解析器（spec §5.1）。
 *
 * 给定 (user, tenant_id)，返回该用户在该租户下所有 active binding 关联角色 permissions 的并集。
 *
 * 关键点：
 *   - withoutGlobalScopes()：BelongsToTenant 全局 scope 在跨请求场景可能残留旧 tenant；显式去除
 *   - status='active'：revoked 不计入
 *   - JSON 数组解析依赖 Role.permissions 的 array cast
 */
class PermissionResolver
{
    /** @return string[] 权限码扁平列表（去重） */
    public function resolveForUserInTenant(User $user, string $tenantId): array
    {
        return $this->permissionsFromBindings(
            $this->resolveBindingsForUserInTenant($user, $tenantId)
        );
    }

    /**
     * 从已加载的 binding 集合提取权限并集。
     * 给中间件等已经持有 bindings 的调用方复用，避免重复 query。
     *
     * @param  Collection<int, UserRoleBinding>  $bindings
     * @return string[] 权限码扁平列表（去重 + 有序）
     */
    public function permissionsFromBindings(Collection $bindings): array
    {
        $perms = [];
        foreach ($bindings as $b) {
            foreach ($b->role?->permissions ?? [] as $code) {
                $perms[$code] = true;
            }
        }

        $list = array_keys($perms);
        sort($list);
        return $list;
    }

    /** @return Collection<UserRoleBinding> active 绑定集合（含 role 关联预加载） */
    public function resolveBindingsForUserInTenant(User $user, string $tenantId): Collection
    {
        return UserRoleBinding::query()
            ->withoutGlobalScopes()
            ->with('role')
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->get();
    }
}
