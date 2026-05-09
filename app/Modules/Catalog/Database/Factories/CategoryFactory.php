<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\Category;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'owner_type' => 'TENANT',
            'owner_store_id' => null,
            'category_type' => 'BUSINESS',
            'item_type_scope' => 'ALL',
            'parent_id' => null,
            'name' => $this->faker->unique()->words(2, true).' 分类',
            'code' => null,
            'level' => 1,
            'path' => '/',
            'sort_no' => 0,
            'status' => 'active',
        ];
    }

    public function inventory(): static
    {
        return $this->state(['category_type' => 'INVENTORY']);
    }

    public function both(): static
    {
        return $this->state(['category_type' => 'BOTH']);
    }

    public function child(Category $parent): static
    {
        return $this->state([
            'tenant_id' => $parent->tenant_id,
            'owner_type' => $parent->owner_type,
            'owner_store_id' => $parent->owner_store_id,
            'category_type' => $parent->category_type,
            'item_type_scope' => $parent->item_type_scope,
            'parent_id' => $parent->id,
            'level' => $parent->level + 1,
            'path' => $parent->path.$parent->id.'/',
        ]);
    }
}
