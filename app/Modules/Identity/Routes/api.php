<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Identity\Http\Controllers\MeController;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Route;

/*
 * Identity 模块路由占位。
 *
 * /api/__tenant-probe 仅用于中间件功能验证（测试用），不属于产品 API。
 * 后续 Task 9-10 在此追加 login/logout/me/me-memberships 端点。
 */
// 仅在 testing 环境注册：避免生产/线上暴露内部探针端点
if (app()->environment('testing')) {
    Route::prefix('api')->middleware(['auth:sanctum', 'tenant'])->group(function () {
        Route::get('__tenant-probe', function (\Illuminate\Http\Request $request) {
            return response()->json([
                'tenant_id' => app(\App\Support\Tenancy\CurrentTenant::class)->id(),
                'is_platform_impersonation' => $request->attributes->get('is_platform_impersonation'),
                'platform_role_code' => $request->attributes->get('platform_role')?->code,
                'effective_permissions' => $request->attributes->get('effective_permissions'),
                'role_bindings_count' => $request->attributes->get('current_role_bindings')?->count(),
            ]);
        });

        Route::get('__perm-probe', function () {
            return response()->json(['ok' => true]);
        })->middleware('permission:roles.manage');

        Route::post('__perm-probe-write', function () {
            return response()->json(['ok' => true]);
        })->middleware('permission:roles.manage');
    });
}

// 公开端点：登录（throttle 限流防爆破）
Route::prefix('api/auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');
});

// 已登录但不要求 tenant：登出
Route::prefix('api/auth')->middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
});

// 已登录但不要求 tenant：我的身份与所属租户列表
Route::prefix('api')->middleware('auth:sanctum')->group(function () {
    Route::get('me', [MeController::class, 'show']);
    Route::get('me/memberships', [MeController::class, 'memberships']);
});
