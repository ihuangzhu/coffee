<?php

declare(strict_types=1);

use App\Modules\Inventory\Models\TenantInventoryConfig;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $existing = TenantInventoryConfig::query()->pluck('tenant_id')->all();
        Tenant::query()
            ->whereNotIn('id', $existing)
            ->pluck('id')
            ->each(fn ($id) => TenantInventoryConfig::query()->create(['tenant_id' => $id]));
    }

    public function down(): void
    {
    }
};
