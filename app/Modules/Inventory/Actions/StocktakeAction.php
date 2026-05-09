<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Events\StockChanged;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockTxn;
use App\Modules\Inventory\Support\InventoryGuard;
use Illuminate\Support\Facades\DB;

/**
 * 单 SKU 盘点：传入实盘数量，与 balance.available_qty 比对：
 *   delta > 0  → 盘盈：biz_type=STOCKTAKE_PROFIT, direction=IN
 *   delta < 0  → 盘亏：biz_type=STOCKTAKE_LOSS, direction=OUT
 *   delta == 0 → 不写流水，返回 null
 *
 * meta_json 写入：book_qty / actual_qty / note。
 */
class StocktakeAction
{
    /**
     * @return int|null 新 stock_txn id，或 null 表示无差异未写入
     */
    public static function handle(
        string $tenantId,
        string $storeId,
        string $stockOwnerId,
        string $locationId,
        string $skuId,
        string $actualQty,
        string $operatorId,
        ?string $note = null,
    ): ?int {
        InventoryGuard::assertEnabled($tenantId, $storeId, $skuId);

        return DB::transaction(function () use (
            $tenantId, $stockOwnerId, $locationId, $skuId, $actualQty, $operatorId, $note
        ) {
            $balance = StockBalance::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('stock_owner_id', $stockOwnerId)
                ->where('location_id', $locationId)
                ->where('sku_id', $skuId)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                $balance = StockBalance::query()->withoutGlobalScopes()->create([
                    'tenant_id' => $tenantId,
                    'stock_owner_id' => $stockOwnerId,
                    'location_id' => $locationId,
                    'sku_id' => $skuId,
                ]);
                $balance = StockBalance::query()->withoutGlobalScopes()
                    ->whereKey($balance->id)->lockForUpdate()->first();
            }

            $bookQty = (string) $balance->available_qty;
            $delta = bcsub($actualQty, $bookQty, 4);
            $cmp = bccomp($delta, '0', 4);

            if ($cmp === 0) {
                return null;
            }

            $bizType = $cmp > 0 ? 'STOCKTAKE_PROFIT' : 'STOCKTAKE_LOSS';
            $direction = $cmp > 0 ? 'IN' : 'OUT';

            $txn = StockTxn::query()->create([
                'tenant_id' => $tenantId,
                'biz_type' => $bizType,
                'stock_owner_id' => $stockOwnerId,
                'location_id' => $locationId,
                'sku_id' => $skuId,
                'qty_change' => $delta,
                'direction' => $direction,
                'occurred_at' => now(),
                'operator_id' => $operatorId,
                'meta_json' => [
                    'book_qty' => $bookQty,
                    'actual_qty' => $actualQty,
                    'note' => $note,
                ],
            ]);

            $balance->available_qty = $actualQty;
            $balance->version = $balance->version + 1;
            $balance->save();

            StockChanged::dispatch(
                $tenantId, $stockOwnerId, $locationId, $skuId, (int) $txn->id, $bizType
            );

            return (int) $txn->id;
        });
    }
}
