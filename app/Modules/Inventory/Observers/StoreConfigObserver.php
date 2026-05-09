<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Observers;

use App\Modules\Tenancy\Models\Store;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Inventory\Models\StoreInventoryConfig;

/**
 * Store 创建时连带建立：
 * - store_inventory_configs 行
 * - stock_owners 行（owner_type=STORE 指向 store）
 * - stock_locations 行（默认库位 'DEFAULT'）
 *
 * 三件事在同一事务边界内（Laravel 自动包 Observer 在 model save 事务里）。
 */
class StoreConfigObserver
{
    public function created(Store $store): void
    {
        StoreInventoryConfig::query()->create([
            'tenant_id' => $store->tenant_id,
            'store_id' => $store->id,
        ]);

        $owner = StockOwner::query()->withoutGlobalScopes()->create([
            'tenant_id' => $store->tenant_id,
            'owner_type' => 'STORE',
            'owner_ref_id' => $store->id,
            'name' => $store->name.' 主仓',
            'status' => 'active',
        ]);

        StockLocation::query()->withoutGlobalScopes()->create([
            'tenant_id' => $store->tenant_id,
            'stock_owner_id' => $owner->id,
            'location_code' => 'DEFAULT',
            'location_name' => '默认库位',
            'location_type' => 'SHELF',
            'status' => 'active',
        ]);
    }
}
