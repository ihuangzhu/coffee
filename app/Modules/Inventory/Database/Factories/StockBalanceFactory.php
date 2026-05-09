<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Database\Factories;

use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockBalanceFactory extends Factory
{
    protected $model = StockBalance::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'stock_owner_id' => StockOwner::factory(),
            'location_id' => StockLocation::factory(),
            'sku_id' => ItemSku::factory(),
            'available_qty' => 0,
            'reserved_qty' => 0,
            'in_transit_qty' => 0,
            'damaged_qty' => 0,
            'version' => 0,
        ];
    }
}
