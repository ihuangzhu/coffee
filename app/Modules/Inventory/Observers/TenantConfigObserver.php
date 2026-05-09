<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Observers;

use App\Modules\Inventory\Models\TenantInventoryConfig;
use App\Modules\Tenancy\Models\Tenant;

/**
 * 租户创建时自动建一行 tenant_inventory_configs（默认值即迁移列默认）。
 */
class TenantConfigObserver
{
    public function created(Tenant $tenant): void
    {
        TenantInventoryConfig::query()->create([
            'tenant_id' => $tenant->id,
        ]);
    }
}
