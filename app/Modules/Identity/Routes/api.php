<?php

declare(strict_types=1);

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
