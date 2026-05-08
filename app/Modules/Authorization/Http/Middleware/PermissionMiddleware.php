<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Http\Middleware;

use App\Modules\Authorization\Enums\PlatformPermission;
use App\Support\Exceptions\BusinessException;
use Closure;
use Illuminate\Http\Request;

/**
 * 商户域权限校验（spec §5.2，INV-C/D）。
 *
 * 双路径：
 *   - is_platform_impersonation=true：仅消费 platform_role.permissions
 *     · ImpersonateFull → 任意方法放行
 *     · ImpersonateReadOnly + GET/HEAD → 放行
 *     · 其它 → 403 platform.impersonation.method-not-allowed
 *   - is_platform_impersonation=false：仅消费 effective_permissions
 *     · 含目标 permissionKey → 放行
 *     · 不含 → 403 permission.missing
 *
 * 必须挂在 'tenant' 之后（依赖 attributes）。
 */
class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permissionKey)
    {
        if ($request->attributes->get('is_platform_impersonation') === true) {
            return $this->handlePlatformImpersonation($request, $next);
        }

        $effective = $request->attributes->get('effective_permissions') ?? [];
        if (! in_array($permissionKey, $effective, strict: true)) {
            throw new BusinessException(
                'permission.missing',
                "missing permission: {$permissionKey}",
                403,
                ['permission_key' => $permissionKey]
            );
        }

        return $next($request);
    }

    private function handlePlatformImpersonation(Request $request, Closure $next)
    {
        $platformRole = $request->attributes->get('platform_role');

        if (! $platformRole) {
            throw new BusinessException(
                'platform.impersonation.no-role',
                '平台员工尚未分配 platform_role，禁止任何操作',
                403,
            );
        }

        $perms = $platformRole->permissions ?? [];

        if (in_array(PlatformPermission::ImpersonateFull->value, $perms, strict: true)) {
            return $next($request);
        }

        $isReadOnly = in_array(PlatformPermission::ImpersonateReadOnly->value, $perms, strict: true);
        if ($isReadOnly && in_array($request->method(), ['GET', 'HEAD'], strict: true)) {
            return $next($request);
        }

        throw new BusinessException(
            'platform.impersonation.method-not-allowed',
            'platform impersonation does not allow this method',
            403,
            ['method' => $request->method()]
        );
    }
}
