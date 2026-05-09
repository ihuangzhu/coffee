<?php

declare(strict_types=1);

use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockQuant;
use Illuminate\Database\Migrations\Migration;

/**
 * 一次性回填：从 stock_quants 聚合 available_qty 到 stock_balances。
 * 适用历史 dev DB 中 quants/txns 由 factory 直接造、绕过 Action 没写 balances 的情况。
 * reserved / in_transit / damaged 三个桶在第一期未启用，回填留 0。
 */
return new class extends Migration
{
    public function up(): void
    {
        $aggregates = StockQuant::query()->withoutGlobalScopes()
            ->selectRaw('tenant_id, stock_owner_id, location_id, sku_id, sum(qty) as total_qty')
            ->groupBy('tenant_id', 'stock_owner_id', 'location_id', 'sku_id')
            ->get();

        foreach ($aggregates as $a) {
            StockBalance::query()->withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $a->tenant_id,
                    'stock_owner_id' => $a->stock_owner_id,
                    'location_id' => $a->location_id,
                    'sku_id' => $a->sku_id,
                ],
                [
                    'available_qty' => $a->total_qty,
                ],
            );
        }
    }

    public function down(): void
    {
    }
};
