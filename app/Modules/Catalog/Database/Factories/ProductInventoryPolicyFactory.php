<?php
declare(strict_types=1);
namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Catalog\Models\ProductInventoryPolicy;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductInventoryPolicyFactory extends Factory
{
    protected $model = ProductInventoryPolicy::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'sku_id' => ItemSku::factory(),
            'inventory_track_type' => 'FINISHED_GOOD',
            'stock_deduct_mode' => 'MANUAL_DEDUCT',
            'allow_negative_stock' => false,
            'batch_required' => false,
            'expiry_required' => false,
        ];
    }
}
