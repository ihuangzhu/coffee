<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Web 端平台员工守卫：在 `auth` 通过之后再校验 is_platform_admin。
 * 不通过时登出并跳回平台登录页（避免普通用户拿到 platform 页面 schema）。
 */
class EnsurePlatformAdminWeb
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();
        if (! $user || ! $user->is_platform_admin) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/platform/login')->withErrors([
                'phone' => '需要平台员工身份才能访问总后台',
            ]);
        }

        return $next($request);
    }
}
