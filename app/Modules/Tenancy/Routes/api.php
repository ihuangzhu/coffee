<?php

declare(strict_types=1);

use App\Modules\Tenancy\Http\Controllers\StoreController;
use App\Modules\Tenancy\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::get('tenants/current', [TenantController::class, 'current']);
    Route::get('stores', [StoreController::class, 'index']);
});
