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
        $this->rolePerms = [
            'TenantAdmin' => [
                'boms.read', 'boms.create', 'boms.update', 'boms.delete',
                'production.execute', 'production.read',
            ],
            'StoreManager' => [
                'boms.read', 'boms.create', 'boms.update',
                'production.execute', 'production.read',
                // 不允许 boms.delete
            ],
            'StoreClerk' => [
                'boms.read', 'production.read', 'production.execute',
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
        $allBomPerms = array_unique(array_merge(...array_values($this->rolePerms)));

        foreach (array_keys($this->rolePerms) as $code) {
            $role = Role::query()->whereNull('tenant_id')->where('code', $code)->first();
            if ($role === null) {
                continue;
            }

            $role->permissions = array_values(array_diff(
                (array) $role->permissions,
                $allBomPerms,
            ));
            $role->save();
        }
    }
};
