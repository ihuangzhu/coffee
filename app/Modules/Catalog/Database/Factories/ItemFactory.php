<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Item;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemFactory extends Factory
{
    protected $model = Item::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'business_category_id' => null,
            'inventory_category_id' => null,
            'owner_type' => 'TENANT',
            'owner_store_id' => null,
            'item_type' => 'SALE_PRODUCT',
            'item_name' => $this->faker->unique()->words(2, true).' 物料',
            'unit' => $this->faker->randomElement(['PCS', 'BOX', 'G', 'ML']),
            'sku_enabled' => true,
            'inventory_enabled' => true,
            'status' => 'active',
        ];
    }

    /** 关联经营分类（category_type ∈ {BUSINESS, BOTH}） */
    public function withBusinessCategory(Category $category): static
    {
        return $this->state(['business_category_id' => $category->id]);
    }

    /** 关联库存物料分类（category_type ∈ {INVENTORY, BOTH}） */
    public function withInventoryCategory(Category $category): static
    {
        return $this->state(['inventory_category_id' => $category->id]);
    }
}
