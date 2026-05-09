<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Database\Factories;

use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Inventory\Models\Bom;
use App\Modules\Inventory\Models\BomComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

class BomComponentFactory extends Factory
{
    protected $model = BomComponent::class;

    public function definition(): array
    {
        return [
            'bom_id' => Bom::factory(),
            'component_sku_id' => ItemSku::factory(),
            'consume_qty' => $this->faker->randomFloat(2, 0.1, 10),
            'loss_rate' => 0,
            'sequence_no' => $this->faker->numberBetween(0, 99),
        ];
    }
}
