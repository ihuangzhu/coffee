<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Http\Middleware;

use App\Support\Exceptions\BusinessException;
use Closure;
use Illuminate\Http\Request;

/**
 * 平台域权限校验（spec §5.3）。
 *
 * 不依赖 X-Tenant-Id（平台后台不强制选租户）。
 * 直接从 user.platformRole.permissions 检查。
 *
 * 必须挂在 'auth:sanctum, platform_admin' 之后。
 */
class PlatformPermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permissionKey)
    {
        $user = $request->user();
        $perms = $user?->platformRole?->permissions ?? [];

        if (! in_array($permissionKey, $perms, strict: true)) {
            throw new BusinessException(
                'platform.permission.missing',
                "missing platform permission: {$permissionKey}",
                403,
                ['permission_key' => $permissionKey]
            );
        }

        return $next($request);
    }
}
