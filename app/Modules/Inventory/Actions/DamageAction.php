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
 * 单 SKU 报损：物理出库（第一期 damaged_qty 桶不写入）。
 * - qty 必须为正数
 * - 校验 available_qty >= qty（除非 negative_stock 开）
 * - 写 DAMAGE_OUT 流水（qty_change = -qty）
 * - balance.available_qty -= qty（只动 available；spec §6.3 决议）
 */
class DamageAction
{
    public static function handle(
        string $tenantId,
        string $storeId,
        string $stockOwnerId,
        string $locationId,
        string $skuId,
        string $qty,
        int $unitCostCents,
        string $operatorId,
        ?string $reason = null,
    ): int {
        InventoryGuard::assertEnabled($tenantId, $storeId, $skuId);

        if (bccomp($qty, '0', 4) <= 0) {
            throw new BusinessException('INVALID_QTY', '报损数量必须 > 0', 422);
        }

        return DB::transaction(function () use (
            $tenantId, $stockOwnerId, $locationId, $skuId, $qty,
            $unitCostCents, $operatorId, $reason
        ) {
            $balance = StockBalance::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('stock_owner_id', $stockOwnerId)
                ->where('location_id', $locationId)
                ->where('sku_id', $skuId)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                throw new BusinessException('NO_BALANCE', '该 SKU 在此库位无库存记录，不能报损', 422);
            }

            $newAvailable = bcsub((string) $balance->available_qty, $qty, 4);
            if (bccomp($newAvailable, '0', 4) < 0
                && ! InventoryGuard::negativeStockAllowed($tenantId, $skuId)) {
                throw new BusinessException('INSUFFICIENT_STOCK', '库存不足且未开启允许负库存', 422);
            }

            $amountCents = (int) round((float) $qty * $unitCostCents);

            $txn = StockTxn::query()->create([
                'tenant_id' => $tenantId,
                'biz_type' => 'DAMAGE_OUT',
                'stock_owner_id' => $stockOwnerId,
                'location_id' => $locationId,
                'sku_id' => $skuId,
                'qty_change' => '-'.$qty,
                'unit_cost_cents' => $unitCostCents,
                'amount_cents' => $amountCents,
                'direction' => 'OUT',
                'occurred_at' => now(),
                'operator_id' => $operatorId,
                'meta_json' => ['reason' => $reason],
            ]);

            $balance->available_qty = $newAvailable;
            $balance->version = $balance->version + 1;
            $balance->save();

            StockChanged::dispatch(
                $tenantId, $stockOwnerId, $locationId, $skuId, (int) $txn->id, 'DAMAGE_OUT'
            );

            return (int) $txn->id;
        });
    }
}
