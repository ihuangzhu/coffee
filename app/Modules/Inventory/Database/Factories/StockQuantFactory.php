<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Database\Factories;

use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Inventory\Models\StockQuant;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockQuantFactory extends Factory
{
    protected $model = StockQuant::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'stock_owner_id' => StockOwner::factory(),
            'location_id' => StockLocation::factory(),
            'sku_id' => ItemSku::factory(),
            'batch_no' => null,
            'expiry_date' => null,
            'unit_cost_cents' => $this->faker->numberBetween(50, 5000),
            'qty' => $this->faker->randomFloat(2, 0, 1000),
        ];
    }
}
