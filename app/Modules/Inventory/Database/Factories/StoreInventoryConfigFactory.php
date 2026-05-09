<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Database\Factories;

use App\Modules\Inventory\Models\StoreInventoryConfig;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class StoreInventoryConfigFactory extends Factory
{
    protected $model = StoreInventoryConfig::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'store_id' => Store::factory(),
            'inventory_enabled' => true,
            'multi_location_enabled' => false,
            'default_stock_mode' => 'SIMPLE',
            'production_enabled' => false,
            'allow_direct_stock_adjustment' => true,
        ];
    }
}
