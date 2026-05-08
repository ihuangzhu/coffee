<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Database\Factories;

use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->city().'店',
            'status' => 'active',
        ];
    }
}
