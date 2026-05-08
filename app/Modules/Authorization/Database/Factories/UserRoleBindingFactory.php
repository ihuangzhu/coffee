<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Database\Factories;

use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\Models\UserRoleBinding;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserRoleBindingFactory extends Factory
{
    protected $model = UserRoleBinding::class;

    public function definition(): array
    {
        $tenant = Tenant::factory()->create();

        return [
            'user_id' => User::factory(),
            'role_id' => Role::factory()->create(['tenant_id' => $tenant->id])->id,
            'tenant_id' => $tenant->id,
            'store_id' => null,
            'status' => 'active',
            'granted_at' => now(),
        ];
    }

    public function storeScope(): self
    {
        return $this->state(function (array $attrs) {
            $store = Store::factory()->create(['tenant_id' => $attrs['tenant_id']]);
            return ['store_id' => $store->id];
        });
    }

    public function revoked(): self
    {
        return $this->state(['status' => 'revoked']);
    }
}
