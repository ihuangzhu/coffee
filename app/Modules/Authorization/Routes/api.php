<?php

declare(strict_types=1);

use App\Modules\Authorization\Http\Controllers\MePermissionsController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::get('me/permissions', [MePermissionsController::class, 'show']);
});
