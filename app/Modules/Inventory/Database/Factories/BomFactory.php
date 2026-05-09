<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Database\Factories;

use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Inventory\Models\Bom;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class BomFactory extends Factory
{
    protected $model = Bom::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'output_sku_id' => ItemSku::factory(),
            'output_qty' => 1,
            'bom_type' => 'STANDARD',
            'store_id' => null,
            'status' => 'active',
        ];
    }
}
