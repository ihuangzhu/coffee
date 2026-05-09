<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Events\StockChanged;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockTxn;
use App\Support\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;

/**
 * 撤销任一笔 stock_txn：
 * - 写一条反向流水（biz_type 沿用原笔，qty_change 与 direction 取反）
 * - meta_json.cancels_txn_id = 原 txn id
 * - 反向更新 stock_balance.available_qty
 *
 * 校验：
 * - 原 txn 必须存在
 * - 已被撤销过（即存在另一笔 meta.cancels_txn_id == 原 id）则禁止再撤
 * - 对应 balance 行必须存在
 */
class ReverseStockTxnAction
{
    public static function handle(int $txnId, string $operatorId): int
    {
        return DB::transaction(function () use ($txnId, $operatorId) {
            $orig = StockTxn::query()->withoutGlobalScopes()->find($txnId);
            if (! $orig) {
                throw new BusinessException('TXN_NOT_FOUND', "流水 {$txnId} 不存在", 404);
            }

            // SQLite JSON1: use JSON path equality as fallback for whereJsonContains
            $alreadyReversed = StockTxn::query()->withoutGlobalScopes()
                ->where('meta_json->cancels_txn_id', $txnId)
                ->exists();
            if ($alreadyReversed) {
                throw new BusinessException('TXN_ALREADY_REVERSED', "流水 {$txnId} 已被撤销，不能再撤", 422);
            }

            $balance = StockBalance::query()->withoutGlobalScopes()
                ->where('tenant_id', $orig->tenant_id)
                ->where('stock_owner_id', $orig->stock_owner_id)
                ->where('location_id', $orig->location_id)
                ->where('sku_id', $orig->sku_id)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                throw new BusinessException('NO_BALANCE', '原流水对应 balance 行不存在', 422);
            }

            $reverseQty = bcmul((string) $orig->qty_change, '-1', 4);

            $reverseDirection = match ($orig->direction->value) {
                'IN' => 'OUT',
                'OUT' => 'IN',
                'FREEZE' => 'RELEASE',
                'RELEASE' => 'FREEZE',
                default => 'OUT',
            };

            $reverseTxn = StockTxn::query()->create([
                'tenant_id' => $orig->tenant_id,
                'biz_type' => $orig->biz_type->value,
                'biz_order_type' => $orig->biz_order_type,
                'biz_order_id' => $orig->biz_order_id,
                'stock_owner_id' => $orig->stock_owner_id,
                'location_id' => $orig->location_id,
                'sku_id' => $orig->sku_id,
                'qty_change' => $reverseQty,
                'unit_cost_cents' => $orig->unit_cost_cents,
                'amount_cents' => $orig->amount_cents !== null ? -$orig->amount_cents : null,
                'direction' => $reverseDirection,
                'occurred_at' => now(),
                'operator_id' => $operatorId,
                'meta_json' => ['cancels_txn_id' => $txnId],
            ]);

            $balance->available_qty = bcadd((string) $balance->available_qty, $reverseQty, 4);
            $balance->version = $balance->version + 1;
            $balance->save();

            StockChanged::dispatch(
                $orig->tenant_id, $orig->stock_owner_id, $orig->location_id,
                $orig->sku_id, (int) $reverseTxn->id, $orig->biz_type->value
            );

            return (int) $reverseTxn->id;
        });
    }
}
