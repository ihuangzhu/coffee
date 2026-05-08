<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Database\Factories;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company().' 咖啡',
            'status' => 'active',
        ];
    }

    public function disabled(): self
    {
        return $this->state(['status' => 'disabled']);
    }
}
