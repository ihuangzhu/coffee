<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Database\Factories;

use App\Modules\Authorization\Models\PlatformRole;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlatformRoleFactory extends Factory
{
    protected $model = PlatformRole::class;

    public function definition(): array
    {
        return [
            'name' => fake()->jobTitle(),
            'code' => 'CustomPlatformRole_'.fake()->bothify('??##'),
            'permissions' => [],
            'is_system' => false,
        ];
    }
}
