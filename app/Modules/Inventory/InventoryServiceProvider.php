<?php

declare(strict_types=1);

namespace App\Modules\Inventory;

use App\Modules\Inventory\Observers\TenantConfigObserver;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\ModuleServiceProvider;

class InventoryServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__;
    }

    public function boot(): void
    {
        parent::boot();
        Tenant::observe(TenantConfigObserver::class);
    }
}
