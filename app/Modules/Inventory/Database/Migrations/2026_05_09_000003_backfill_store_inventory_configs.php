<?php

declare(strict_types=1);

use App\Modules\Inventory\Models\StoreInventoryConfig;
use App\Modules\Tenancy\Models\Store;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $existing = StoreInventoryConfig::query()->pluck('store_id')->all();
        Store::query()->withoutGlobalScopes()
            ->whereNotIn('id', $existing)
            ->get(['id', 'tenant_id'])
            ->each(fn ($s) => StoreInventoryConfig::query()->create([
                'tenant_id' => $s->tenant_id,
                'store_id' => $s->id,
            ]));
    }

    public function down(): void
    {
    }
};
