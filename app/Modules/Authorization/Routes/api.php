<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
 * Authorization 模块路由。
 *
 * 后续 Task 在此追加：
 *   - 商户域端点（/api/roles, /api/users, /api/users/{id}/role-bindings, /api/role-bindings/{id}, /api/me/permissions）
 *   - 平台域端点（/api/platform/roles, /api/platform/users/{id}/platform-role）
 */

Route::prefix('api')->middleware(['auth:sanctum', 'tenant'])->group(function () {
    // 端点在 Task 13~ 加入
});
