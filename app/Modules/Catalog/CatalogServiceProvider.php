<?php

declare(strict_types=1);

namespace App\Modules\Catalog;

use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Catalog\Observers\ItemSkuObserver;
use App\Support\ModuleServiceProvider;

class CatalogServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__;
    }

    public function boot(): void
    {
        parent::boot();
        ItemSku::observe(ItemSkuObserver::class);
    }
}
