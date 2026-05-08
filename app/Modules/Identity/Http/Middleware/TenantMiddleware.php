<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Authorization\Services\PermissionResolver;
use App\Modules\Identity\Models\Membership;
use App\Support\Tenancy\CurrentMembership;
use App\Support\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;

/**
 * 租户解析中间件（spec §5.1）。
 *
 * 职责：
 *   1. 强制 X-Tenant-Id header 存在（否则 403）
 *   2. 平台员工（is_platform_admin=true）→ 直接信任 header；platform_role 注入；
 *      attributes：is_platform_impersonation=true / platform_role / 其余为 null
 *   3. 普通员工 → 必须有 status=active membership；解析 effective_permissions
 *      attributes：is_platform_impersonation=false / current_role_bindings / effective_permissions
 *   4. 通过后，CurrentTenant / CurrentMembership / request attributes 全部就绪
 *
 * INV-C：is_platform_impersonation=true 时不消费 role_bindings
 * INV-D：is_platform_impersonation=false 时不消费 platform_role
 */
class TenantMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $tenantId = $request->header('X-Tenant-Id');
        if (! $tenantId || ! is_string($tenantId)) {
            return response()->json(['error' => 'X-Tenant-Id header required'], 403);
        }

        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        if ($user->is_platform_admin) {
            app(CurrentTenant::class)->set($tenantId);
            app(CurrentMembership::class)->set(null);
            $request->attributes->set('current_membership', null);
            $request->attributes->set('is_platform_impersonation', true);
            $request->attributes->set('platform_role', $user->platformRole);
            $request->attributes->set('current_role_bindings', null);
            $request->attributes->set('effective_permissions', null);

            return $next($request);
        }

        // 显式 withoutGlobalScopes：membership 是"该 user 在该 tenant 是否有绑定"的元数据查询，
        // 不应受 BelongsToTenant 全局 scope 限制（CurrentTenant 此时可能仍是上一次请求残留值）。
        $membership = Membership::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first();

        if (! $membership) {
            return response()->json(['error' => 'no active membership'], 403);
        }

        app(CurrentTenant::class)->set($tenantId);
        app(CurrentMembership::class)->set($membership);

        $resolver = app(PermissionResolver::class);
        $bindings = $resolver->resolveBindingsForUserInTenant($user, $tenantId);
        $effective = $resolver->permissionsFromBindings($bindings);

        $request->attributes->set('current_membership', $membership);
        $request->attributes->set('is_platform_impersonation', false);
        $request->attributes->set('platform_role', null);
        $request->attributes->set('current_role_bindings', $bindings);
        $request->attributes->set('effective_permissions', $effective);

        return $next($request);
    }
}
