<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Database\Factories;

use App\Modules\Inventory\Models\TenantInventoryConfig;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantInventoryConfigFactory extends Factory
{
    protected $model = TenantInventoryConfig::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'inventory_enabled' => true,
            'multi_location_enabled' => false,
            'production_enabled' => false,
            'purchase_enabled' => false,
            'transfer_enabled' => false,
            'stocktaking_enabled' => true,
            'negative_stock_allowed' => false,
            'inventory_cost_method' => 'MOVING_AVG',
            'expiry_management_enabled' => false,
            'batch_management_enabled' => false,
            'auto_deduct_raw_material_enabled' => false,
        ];
    }
}
