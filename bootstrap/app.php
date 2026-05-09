<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Modules\Authorization\Http\Middleware\PermissionMiddleware;
use App\Modules\Authorization\Http\Middleware\PlatformPermissionMiddleware;
use App\Modules\Identity\Http\Middleware\EnsurePlatformAdminWeb;
use App\Modules\Identity\Http\Middleware\PlatformAdminMiddleware;
use App\Modules\Identity\Http\Middleware\TenantMiddleware;
use App\Support\Exceptions\BusinessException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => TenantMiddleware::class,
            'platform_admin' => PlatformAdminMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'platform_permission' => PlatformPermissionMiddleware::class,
            'web.platform_admin' => EnsurePlatformAdminWeb::class,
        ]);
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
        $middleware->redirectGuestsTo(fn ($request) => $request->is('platform/*') ? '/platform/login' : '/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (BusinessException $e, $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'code' => $e->errorCode(),
                'message' => $e->getMessage(),
                'details' => $e->details(),
            ], $e->httpStatus());
        });
    })->create();
