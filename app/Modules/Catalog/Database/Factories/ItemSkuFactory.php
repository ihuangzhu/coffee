<?php
declare(strict_types=1);
namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemSkuFactory extends Factory
{
    protected $model = ItemSku::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'item_id' => Item::factory(),
            'spec_json' => [],
            'barcode' => null,
            'sale_price_cents' => $this->faker->numberBetween(100, 9999),
            'cost_price_cents' => $this->faker->numberBetween(50, 5000),
            'inventory_enabled' => true,
        ];
    }
}
