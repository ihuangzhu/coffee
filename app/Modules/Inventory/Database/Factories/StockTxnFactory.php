<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Database\Factories;

use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Inventory\Models\StockTxn;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockTxnFactory extends Factory
{
    protected $model = StockTxn::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'biz_type' => 'ADJUSTMENT',
            'biz_order_type' => null,
            'biz_order_id' => null,
            'stock_owner_id' => StockOwner::factory(),
            'location_id' => StockLocation::factory(),
            'sku_id' => ItemSku::factory(),
            'qty_change' => $this->faker->randomFloat(2, -100, 100),
            'unit_cost_cents' => null,
            'amount_cents' => null,
            'direction' => 'IN',
            'occurred_at' => now(),
            'operator_id' => User::factory(),
            'meta_json' => ['subtype' => 'MANUAL'],
        ];
    }
}
