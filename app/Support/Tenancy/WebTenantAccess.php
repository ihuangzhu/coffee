<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;

/**
 * Web/Inertia 上下文里"当前用户能看到哪些租户/门店"的查询封装。
 *
 * - 平台员工：见所有 active 租户/门店（impersonation 语义）
 * - 普通用户：仅其 active membership 覆盖到的范围
 *   - tenant 级 membership（store_id = NULL）→ 当前租户全部 active 门店
 *   - store 级 membership → 仅这些 store
 *
 * 与 `BelongsToTenant` 全局作用域无关，所有查询都 `withoutGlobalScopes()`，
 * 因为本类被 HandleInertiaRequests 调用，运行在 tenant 中间件之前。
 */
class WebTenantAccess
{
    /**
     * @return array<int, array{id: string, name: string}>
     */
    public static function availableTenants(User $user): array
    {
        $query = Tenant::query()->where('status', 'active')->orderBy('name');

        if (! $user->is_platform_admin) {
            $tenantIds = Membership::query()
                ->withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->pluck('tenant_id')
                ->unique()
                ->all();
            $query->whereIn('id', $tenantIds);
        }

        return $query->get(['id', 'name'])
            ->map(fn (Tenant $t) => ['id' => $t->id, 'name' => $t->name])
            ->all();
    }

    /**
     * 当前用户在指定 tenant 下可访问的门店列表。
     *
     * @return array<int, array{id: string, name: string}>
     */
    public static function availableStores(User $user, string $tenantId): array
    {
        $query = Store::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('name');

        if (! $user->is_platform_admin) {
            $memberships = Membership::query()
                ->withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->get(['store_id']);

            if ($memberships->isEmpty()) {
                return [];
            }

            // 任一 membership 是租户级（store_id NULL）→ 全店可见，不追加 WHERE
            $hasTenantLevel = $memberships->contains(fn ($m) => $m->store_id === null);
            if (! $hasTenantLevel) {
                $storeIds = $memberships->pluck('store_id')->filter()->unique()->all();
                $query->whereIn('id', $storeIds);
            }
        }

        return $query->get(['id', 'name'])
            ->map(fn (Store $s) => ['id' => $s->id, 'name' => $s->name])
            ->all();
    }
}
