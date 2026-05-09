<?php

declare(strict_types=1);

namespace App\Modules\Catalog;

use App\Support\ModuleServiceProvider;

class CatalogServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__;
    }
}
