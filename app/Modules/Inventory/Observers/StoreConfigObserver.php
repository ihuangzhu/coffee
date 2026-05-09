<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Observers;

use App\Modules\Inventory\Models\StoreInventoryConfig;
use App\Modules\Tenancy\Models\Store;

class StoreConfigObserver
{
    public function created(Store $store): void
    {
        StoreInventoryConfig::query()->create([
            'tenant_id' => $store->tenant_id,
            'store_id' => $store->id,
        ]);
    }
}
