<?php

declare(strict_types=1);

use App\Modules\Authorization\Http\Controllers\MePermissionsController;
use App\Modules\Authorization\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::get('me/permissions', [MePermissionsController::class, 'show']);

    Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.read');
    Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.manage');
});
