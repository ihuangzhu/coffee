<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Database\Factories;

use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class StockOwnerFactory extends Factory
{
    protected $model = StockOwner::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'owner_type' => 'STORE',
            'owner_ref_id' => (string) Str::ulid(),
            'name' => $this->faker->company().' 仓',
            'status' => 'active',
        ];
    }

    public function forStore(Store $store): static
    {
        return $this->state(fn () => [
            'tenant_id' => $store->tenant_id,
            'owner_type' => 'STORE',
            'owner_ref_id' => $store->id,
            'name' => $store->name.' 主仓',
        ]);
    }
}
