<?php

declare(strict_types=1);

namespace App\Modules\Identity\Database\Factories;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserOrgRel;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserOrgRelFactory extends Factory
{
    protected $model = UserOrgRel::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tenant_id' => Tenant::factory(),
            'store_id' => null,
            'status' => 'active',
            'joined_at' => now(),
        ];
    }

    public function left(): self
    {
        return $this->state(['status' => 'left']);
    }
}
