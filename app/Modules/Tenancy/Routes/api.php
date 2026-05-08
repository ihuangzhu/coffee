<?php

declare(strict_types=1);

use App\Modules\Tenancy\Http\Controllers\Platform\PlatformStoreController;
use App\Modules\Tenancy\Http\Controllers\Platform\PlatformTenantController;
use App\Modules\Tenancy\Http\Controllers\Platform\PlatformUserController;
use App\Modules\Tenancy\Http\Controllers\StoreController;
use App\Modules\Tenancy\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::get('tenants/current', [TenantController::class, 'current']);
    Route::get('stores', [StoreController::class, 'index']);
});

Route::prefix('api/platform')->middleware(['auth:sanctum', 'platform_admin'])->group(function () {
    Route::post('tenants', [PlatformTenantController::class, 'store'])
        ->middleware('platform_permission:platform.tenants.manage');
    Route::post('tenants/{tenantId}/stores', [PlatformStoreController::class, 'store'])
        ->middleware('platform_permission:platform.stores.manage');
    Route::post('tenants/{tenantId}/users', [PlatformUserController::class, 'store'])
        ->middleware('platform_permission:platform.users.manage');
});
