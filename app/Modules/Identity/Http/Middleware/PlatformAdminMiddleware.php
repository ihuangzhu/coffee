<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * 平台员工守卫（首期简化版）。
 *
 * 仅检查 user.is_platform_admin === true。后续 RBAC 迭代将扩展为
 * `platform_admin:<perm>` 形态，校验 platform_role.permissions。
 */
class PlatformAdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }
        if (! $user->is_platform_admin) {
            return response()->json(['error' => 'platform admin required'], 403);
        }

        return $next($request);
    }
}
