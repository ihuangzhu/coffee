<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Inertia 请求处理中间件：
 * - 指定 Inertia 根 Blade 视图为 'app'
 * - 注入应用名作为全局共享 prop
 */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'appName' => config('app.name'),
        ]);
    }
}
