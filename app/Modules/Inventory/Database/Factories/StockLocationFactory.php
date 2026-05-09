<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Database\Factories;

use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockLocationFactory extends Factory
{
    protected $model = StockLocation::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'stock_owner_id' => StockOwner::factory(),
            'location_code' => strtoupper($this->faker->unique()->bothify('LOC###')),
            'location_name' => $this->faker->word().' 货架',
            'location_type' => 'SHELF',
            'status' => 'active',
        ];
    }
}
