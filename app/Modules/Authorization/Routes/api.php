<?php

declare(strict_types=1);

use App\Modules\Authorization\Http\Controllers\MePermissionsController;
use App\Modules\Authorization\Http\Controllers\Platform\PlatformRoleController;
use App\Modules\Authorization\Http\Controllers\Platform\UserPlatformRoleController;
use App\Modules\Authorization\Http\Controllers\RoleBindingController;
use App\Modules\Authorization\Http\Controllers\RoleController;
use App\Modules\Authorization\Http\Controllers\UserListController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::get('me/permissions', [MePermissionsController::class, 'show']);

    Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.read');
    Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.manage');
    Route::patch('roles/{roleId}', [RoleController::class, 'update'])->middleware('permission:roles.manage');
    Route::delete('roles/{roleId}', [RoleController::class, 'destroy'])->middleware('permission:roles.manage');

    Route::get('users', [UserListController::class, 'index'])->middleware('permission:users.read');

    Route::post('users/{userId}/role-bindings', [RoleBindingController::class, 'store'])
        ->middleware('permission:users.assign-role');
    Route::delete('role-bindings/{bindingId}', [RoleBindingController::class, 'destroy'])
        ->middleware('permission:users.assign-role');
});

Route::prefix('api/platform')->middleware(['auth:sanctum', 'platform_admin'])->group(function () {
    Route::get('roles', [PlatformRoleController::class, 'index'])
        ->middleware('platform_permission:platform.roles.manage');
    Route::post('roles', [PlatformRoleController::class, 'store'])
        ->middleware('platform_permission:platform.roles.manage');
    Route::patch('roles/{id}', [PlatformRoleController::class, 'update'])
        ->middleware('platform_permission:platform.roles.manage');
    Route::delete('roles/{id}', [PlatformRoleController::class, 'destroy'])
        ->middleware('platform_permission:platform.roles.manage');
    Route::patch('users/{userId}/platform-role', [
        UserPlatformRoleController::class, 'update',
    ])->middleware('platform_permission:platform.roles.manage');
});
