<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** @var array<string, list<string>> */
    private array $rolePerms = [];

    public function __construct()
    {
        $all = [
            'items.read', 'items.write',
            'item_skus.read', 'item_skus.write',
            'categories.read', 'categories.write',
            'inventory.read', 'inventory.adjust',
            'stocktake.write', 'damage.write',
            'stock_txn.read', 'stock_txn.reverse',
            'inventory_config.read', 'inventory_config.update',
            'inventory_policy.read', 'inventory_policy.update',
        ];

        $this->rolePerms = [
            'TenantAdmin' => $all,

            'StoreManager' => array_values(array_filter($all, static fn (string $p) => ! in_array($p, [
                'inventory_config.update',
                'inventory_policy.update',
            ], true))),

            'StoreClerk' => [
                'items.read',
                'item_skus.read',
                'categories.read',
                'inventory.read',
                'inventory.adjust',
                'stocktake.write',
                'stock_txn.read',
                'inventory_config.read',
            ],
        ];
    }

    public function up(): void
    {
        foreach ($this->rolePerms as $code => $perms) {
            $role = Role::query()->whereNull('tenant_id')->where('code', $code)->first();
            if ($role === null) {
                continue;
            }

            $role->permissions = array_values(array_unique(array_merge(
                (array) $role->permissions,
                $perms,
            )));
            $role->save();
        }
    }

    public function down(): void
    {
        $allInventoryPerms = array_merge(...array_values($this->rolePerms));
        $allInventoryPerms = array_unique($allInventoryPerms);

        foreach (array_keys($this->rolePerms) as $code) {
            $role = Role::query()->whereNull('tenant_id')->where('code', $code)->first();
            if ($role === null) {
                continue;
            }

            $role->permissions = array_values(array_diff(
                (array) $role->permissions,
                $allInventoryPerms,
            ));
            $role->save();
        }
    }
};
