<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Observers;

use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Inventory\Models\StoreInventoryConfig;
use App\Modules\Tenancy\Models\Store;

/**
 * Store 创建时连带建立：
 * - store_inventory_configs 行
 * - stock_owners 行（owner_type=STORE 指向该 store）
 *
 * Task 10 还会在此追加 stock_locations 默认库位创建逻辑。
 */
class StoreConfigObserver
{
    public function created(Store $store): void
    {
        StoreInventoryConfig::query()->create([
            'tenant_id' => $store->tenant_id,
            'store_id' => $store->id,
        ]);

        StockOwner::query()->withoutGlobalScopes()->create([
            'tenant_id' => $store->tenant_id,
            'owner_type' => 'STORE',
            'owner_ref_id' => $store->id,
            'name' => $store->name.' 主仓',
            'status' => 'active',
        ]);
    }
}
