<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Database\Factories;

use App\Modules\Authorization\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->jobTitle(),
            'code' => 'CustomRole_'.fake()->bothify('??##'),
            'scope' => 'tenant',
            'permissions' => [],
            'is_system' => false,
        ];
    }

    public function systemTemplate(): self
    {
        return $this->state([
            'tenant_id' => null,
            'is_system' => true,
        ]);
    }

    public function storeScope(): self
    {
        return $this->state(['scope' => 'store']);
    }
}
