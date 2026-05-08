<?php

declare(strict_types=1);

namespace App\Modules\Tenancy;

use App\Support\ModuleServiceProvider;

/**
 * Tenancy 模块服务提供者。
 *
 * 由 ModuleServiceProvider 基类自动加载 Routes/api.php + Database/Migrations。
 */
class TenancyServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__;
    }
}
