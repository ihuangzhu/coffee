<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Support\ModuleServiceProvider;

/**
 * Identity 模块服务提供者。
 *
 * 由 ModuleServiceProvider 基类自动加载 Routes/api.php + Database/Migrations。
 */
class IdentityServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__;
    }
}
