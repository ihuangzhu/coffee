<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Models\UserOrgRel;
use App\Support\Tenancy\CurrentMembership;
use App\Support\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;

/**
 * 租户解析中间件。
 *
 * 职责：
 *   1. 强制 X-Tenant-Id header 存在（否则 403）
 *   2. 平台员工（is_platform_admin=true）→ 直接信任 header，注入 CurrentTenant
 *      并标记 is_platform_impersonation=true（具体能否做事由后续 PermissionMiddleware 判定）
 *   3. 普通员工 → 必须在该 tenant 拥有 status=active 的 user_org_rel；否则 403
 *   4. 通过后，CurrentTenant / CurrentMembership / request attributes 全部就绪
 *
 * 必须挂在 auth:sanctum 之后；后续 permission middleware 必须挂在本中间件之后。
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
            // 理论上 auth:sanctum 已挡住，兜底防御
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        if ($user->is_platform_admin) {
            app(CurrentTenant::class)->set($tenantId);
            app(CurrentMembership::class)->set(null);
            $request->attributes->set('current_membership', null);
            $request->attributes->set('is_platform_impersonation', true);

            return $next($request);
        }

        // 显式 withoutGlobalScopes：membership 是"该 user 在该 tenant 是否有绑定"的元数据查询，
        // 不应受 BelongsToTenant 全局 scope 限制（CurrentTenant 此时可能仍是上一次请求残留值）。
        $membership = UserOrgRel::query()
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
        $request->attributes->set('current_membership', $membership);
        $request->attributes->set('is_platform_impersonation', false);

        return $next($request);
    }
}
