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
Route::prefix('api')->middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::get('__tenant-probe', function () {
        return response()->json([
            'tenant_id' => app(CurrentTenant::class)->id(),
            'is_platform_impersonation' => request()->attributes->get('is_platform_impersonation'),
        ]);
    });
});

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
