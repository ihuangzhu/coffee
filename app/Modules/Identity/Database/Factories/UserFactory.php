<?php

declare(strict_types=1);

namespace App\Modules\Identity\Database\Factories;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '13'.fake()->numerify('#########'),
            'password' => 'password',
            'status' => 'active',
            'is_platform_admin' => false,
        ];
    }

    public function platformAdmin(): self
    {
        return $this->state(['is_platform_admin' => true]);
    }
}
