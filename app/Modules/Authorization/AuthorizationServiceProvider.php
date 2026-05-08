<?php

declare(strict_types=1);

namespace App\Modules\Authorization;

use App\Support\ModuleServiceProvider;

/**
 * Authorization 模块服务提供者。
 *
 * 由 ModuleServiceProvider 基类自动加载 Routes/api.php + Database/Migrations。
 */
class AuthorizationServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__;
    }
}
