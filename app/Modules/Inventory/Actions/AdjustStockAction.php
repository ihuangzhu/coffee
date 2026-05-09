<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Events\StockChanged;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockTxn;
use App\Modules\Inventory\Support\InventoryGuard;
use App\Support\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;

/**
 * 手动库存调整：写一笔 ADJUSTMENT 流水 + 同步 stock_balance.available_qty。
 * subtype = 'INITIAL' 表示首次入库；'MANUAL' 表示日常加减。
 *
 * 流程：
 *   1. InventoryGuard 5 层 toggle 校验
 *   2. 行级锁定（或新建）stock_balance 行
 *   3. 校验 negative_stock 规则
 *   4. INSERT stock_txns
 *   5. UPDATE stock_balances.available_qty + version+=1
 *   6. emit StockChanged
 */
class AdjustStockAction
{
    /**
     * @param  string  $tenantId  租户 ID
     * @param  string  $storeId  门店 ID（用于 toggle 校验）
     * @param  string  $stockOwnerId  库存主体 ID
     * @param  string  $locationId  库位 ID
     * @param  string  $skuId  SKU ID
     * @param  string  $qtyChange  变动数量（字符串保精度；正数 IN，负数 OUT）
     * @param  string  $direction  'IN' | 'OUT'
     * @param  string  $subtype  'INITIAL' | 'MANUAL'
     * @param  string  $operatorId  操作人 user.id
     * @param  array<string, mixed>  $extraMeta  追加 meta_json 字段
     * @return int 新写入的 stock_txn id
     */
    public static function handle(
        string $tenantId,
        string $storeId,
        string $stockOwnerId,
        string $locationId,
        string $skuId,
        string $qtyChange,
        string $direction,
        string $subtype,
        string $operatorId,
        array $extraMeta = [],
    ): int {
        InventoryGuard::assertEnabled($tenantId, $storeId, $skuId);

        if (! in_array($direction, ['IN', 'OUT'], true)) {
            throw new BusinessException('INVALID_DIRECTION', "direction 必须是 IN 或 OUT，实际：{$direction}", 422);
        }
        if (! in_array($subtype, ['INITIAL', 'MANUAL'], true)) {
            throw new BusinessException('INVALID_SUBTYPE', "subtype 必须是 INITIAL 或 MANUAL，实际：{$subtype}", 422);
        }

        return DB::transaction(function () use (
            $tenantId, $stockOwnerId, $locationId, $skuId, $qtyChange,
            $direction, $subtype, $operatorId, $extraMeta
        ) {
            // 行级锁
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
                // re-lock
                $balance = StockBalance::query()->withoutGlobalScopes()
                    ->whereKey($balance->id)->lockForUpdate()->first();
            }

            $newAvailable = bcadd((string) $balance->available_qty, $qtyChange, 4);
            if (bccomp($newAvailable, '0', 4) < 0
                && ! InventoryGuard::negativeStockAllowed($tenantId, $skuId)) {
                throw new BusinessException('INSUFFICIENT_STOCK', '库存不足且未开启允许负库存', 422);
            }

            $txn = StockTxn::query()->create([
                'tenant_id' => $tenantId,
                'biz_type' => 'ADJUSTMENT',
                'stock_owner_id' => $stockOwnerId,
                'location_id' => $locationId,
                'sku_id' => $skuId,
                'qty_change' => $qtyChange,
                'direction' => $direction,
                'occurred_at' => now(),
                'operator_id' => $operatorId,
                'meta_json' => array_merge(['subtype' => $subtype], $extraMeta),
            ]);

            $balance->available_qty = $newAvailable;
            $balance->version = $balance->version + 1;
            $balance->save();

            StockChanged::dispatch(
                $tenantId, $stockOwnerId, $locationId, $skuId, (int) $txn->id, 'ADJUSTMENT'
            );

            return (int) $txn->id;
        });
    }
}
