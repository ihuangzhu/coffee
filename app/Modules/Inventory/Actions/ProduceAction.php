<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Catalog\Models\ProductInventoryPolicy;
use App\Modules\Inventory\Events\StockChanged;
use App\Modules\Inventory\Models\Bom;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Inventory\Models\StockTxn;
use App\Modules\Inventory\Models\TenantInventoryConfig;
use App\Modules\Inventory\Support\InventoryGuard;
use App\Support\Eloquent\TenantScope;
use App\Support\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;

/**
 * 生产入库（按 BOM 一次产出 N 批）。
 *
 * 流程：
 *   1.  校验 batchQty > 0
 *   2.  加载 BOM（with components），不存在 / 已停用 / 已软删 → BOM_NOT_FOUND
 *   3.  STORE_CUSTOM 检查 store_id 匹配 → BOM_STORE_MISMATCH
 *   4.  components 非空 → BOM_NO_COMPONENTS
 *   5.  InventoryGuard 5 层 toggle：output sku + 每个 component sku
 *   6.  DB::transaction：
 *       a. 解析 StockOwner + DEFAULT StockLocation
 *       b. 计算消耗计划（含 loss_rate × batchQty）
 *       c. 按 sku_id 字典序排序（防死锁）
 *       d. lockForUpdate component balances + output balance（不存在则 create+lock）
 *       e. 双层 negative_stock AND 校验
 *       f. 写 N 条 PRODUCTION_CONSUME txn + 更新 component balance
 *       g. 写 1 条 PRODUCTION_OUTPUT txn + 更新 output balance
 *       h. DB::afterCommit 发 N+1 个 StockChanged 事件
 *       i. return ['consume_txn_ids' => [...], 'output_txn_id' => ...]
 */
class ProduceAction
{
    /**
     * @param  string  $tenantId    租户 ID
     * @param  string  $storeId     门店 ID
     * @param  string  $bomId       BOM ID
     * @param  string  $batchQty    按 BOM output_qty 的倍数（bcmath 字符串，> 0）
     * @param  string  $operatorId  操作人 user.id，NOT NULL
     * @param  string|null  $sourceLocationId  原料出库位（null → DEFAULT）
     * @param  string|null  $outputLocationId  成品入库位（null → DEFAULT）
     * @param  array<string, mixed>  $extraMeta  附加 meta_json
     * @return array{consume_txn_ids: list<int>, output_txn_id: int}
     */
    public static function handle(
        string $tenantId,
        string $storeId,
        string $bomId,
        string $batchQty,
        string $operatorId,
        ?string $sourceLocationId = null,
        ?string $outputLocationId = null,
        array $extraMeta = [],
    ): array {
        // Step 1: 校验 batchQty > 0
        if (bccomp($batchQty, '0', 4) <= 0) {
            throw new BusinessException('INVALID_BATCH_QTY', "batchQty 必须 > 0，实际：{$batchQty}", 422);
        }

        // Step 2: 加载 BOM（保留 SoftDeletingScope，仅移除 TenantScope）
        $bom = Bom::query()
            ->withoutGlobalScopes([TenantScope::class])
            ->with(['components'])
            ->where('tenant_id', $tenantId)
            ->where('id', $bomId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();

        if (! $bom) {
            throw new BusinessException('BOM_NOT_FOUND', '配方不存在或已停用', 404);
        }

        // Step 3: STORE_CUSTOM bom store_id 匹配
        if ($bom->bom_type->value === 'STORE_CUSTOM' && $bom->store_id !== $storeId) {
            throw new BusinessException('BOM_STORE_MISMATCH', '门店与配方不匹配', 403);
        }

        // Step 4: 至少一条 component
        if ($bom->components->isEmpty()) {
            throw new BusinessException('BOM_NO_COMPONENTS', '配方无组件', 422);
        }

        // Step 5: InventoryGuard 5 层 toggle 校验
        InventoryGuard::assertEnabled($tenantId, $storeId, $bom->output_sku_id);
        foreach ($bom->components as $component) {
            InventoryGuard::assertEnabled($tenantId, $storeId, $component->component_sku_id);
        }

        // Step 6: DB::transaction
        return DB::transaction(function () use (
            $tenantId, $storeId, $bom, $batchQty, $operatorId,
            $sourceLocationId, $outputLocationId, $extraMeta
        ) {
            // 6a: 解析 owner + DEFAULT location
            $owner = StockOwner::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('owner_type', 'STORE')
                ->where('owner_ref_id', $storeId)
                ->firstOrFail();

            $defaultLoc = StockLocation::query()->withoutGlobalScopes()
                ->where('stock_owner_id', $owner->id)
                ->where('location_code', 'DEFAULT')
                ->firstOrFail();

            $sourceLocId = $sourceLocationId ?? $defaultLoc->id;
            $outputLocId = $outputLocationId ?? $defaultLoc->id;

            // 总产出量 = bom.output_qty × batchQty
            $totalOutputQty = bcmul((string) $bom->output_qty, $batchQty, 4);

            // 6b: 计算消耗计划（含 loss_rate）
            // 每个 component 实际消耗 = consume_qty × (1 + loss_rate) × batchQty
            $consumePlans = [];
            foreach ($bom->components as $component) {
                $consumePlans[] = [
                    'sku_id' => $component->component_sku_id,
                    'qty'    => bcmul(
                        bcmul(
                            (string) $component->consume_qty,
                            bcadd('1', (string) $component->loss_rate, 4),
                            4
                        ),
                        $batchQty,
                        4
                    ),
                ];
            }

            // 6c: 按 sku_id 字典序排序（防死锁）
            usort($consumePlans, fn ($a, $b) => strcmp($a['sku_id'], $b['sku_id']));

            // 6d: lockForUpdate component balances（不存在视为 null）
            $balances = [];
            foreach ($consumePlans as $plan) {
                $balances[$plan['sku_id']] = StockBalance::query()->withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('stock_owner_id', $owner->id)
                    ->where('location_id', $sourceLocId)
                    ->where('sku_id', $plan['sku_id'])
                    ->lockForUpdate()
                    ->first();
            }

            // lockForUpdate / create output balance
            $outputBalance = StockBalance::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('stock_owner_id', $owner->id)
                ->where('location_id', $outputLocId)
                ->where('sku_id', $bom->output_sku_id)
                ->lockForUpdate()
                ->first();

            if (! $outputBalance) {
                $outputBalance = StockBalance::query()->withoutGlobalScopes()->create([
                    'tenant_id'      => $tenantId,
                    'stock_owner_id' => $owner->id,
                    'location_id'    => $outputLocId,
                    'sku_id'         => $bom->output_sku_id,
                ]);
                // re-lock
                $outputBalance = StockBalance::query()->withoutGlobalScopes()
                    ->whereKey($outputBalance->id)
                    ->lockForUpdate()
                    ->first();
            }

            // 6e: 双层 negative_stock AND 校验
            $tenantCfg = TenantInventoryConfig::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->first();

            foreach ($consumePlans as $plan) {
                $available  = (string) ($balances[$plan['sku_id']]?->available_qty ?? '0');
                $remaining  = bcsub($available, $plan['qty'], 4);

                if (bccomp($remaining, '0', 4) < 0) {
                    $policy    = ProductInventoryPolicy::query()->withoutGlobalScopes()
                        ->where('sku_id', $plan['sku_id'])
                        ->first();
                    $tenantOk  = $tenantCfg && $tenantCfg->negative_stock_allowed;
                    $policyOk  = $policy && $policy->allow_negative_stock;

                    if (! ($tenantOk && $policyOk)) {
                        throw new BusinessException(
                            'INSUFFICIENT_STOCK',
                            "原料 {$plan['sku_id']} 库存不足",
                            422
                        );
                    }
                }
            }

            $occurredAt    = now();
            $consumeTxnIds = [];

            // 6f: 写 N 条 PRODUCTION_CONSUME txn + 更新 component balance
            foreach ($consumePlans as $plan) {
                $negQty = bcsub('0', $plan['qty'], 4);

                $txn = StockTxn::query()->create([
                    'tenant_id'      => $tenantId,
                    'biz_type'       => 'PRODUCTION_CONSUME',
                    'biz_order_type' => 'BOM',
                    'biz_order_id'   => $bom->id,
                    'stock_owner_id' => $owner->id,
                    'location_id'    => $sourceLocId,
                    'sku_id'         => $plan['sku_id'],
                    'qty_change'     => $negQty,
                    'direction'      => 'OUT',
                    'occurred_at'    => $occurredAt,
                    'operator_id'    => $operatorId,
                    'meta_json'      => array_merge($extraMeta, [
                        'bom_id'        => $bom->id,
                        'batch_qty'     => $batchQty,
                        'output_sku_id' => $bom->output_sku_id,
                    ]),
                ]);

                $consumeTxnIds[] = (int) $txn->id;

                if ($balances[$plan['sku_id']]) {
                    $balance                = $balances[$plan['sku_id']];
                    $balance->available_qty = bcsub((string) $balance->available_qty, $plan['qty'], 4);
                    $balance->version      += 1;
                    $balance->save();
                } else {
                    // 允许负库存时 balance 不存在 → 直接创建负值行
                    StockBalance::query()->withoutGlobalScopes()->create([
                        'tenant_id'      => $tenantId,
                        'stock_owner_id' => $owner->id,
                        'location_id'    => $sourceLocId,
                        'sku_id'         => $plan['sku_id'],
                        'available_qty'  => $negQty,
                        'version'        => 1,
                    ]);
                }
            }

            // 6g: 写 1 条 PRODUCTION_OUTPUT txn + 更新 output balance
            $outputTxn = StockTxn::query()->create([
                'tenant_id'      => $tenantId,
                'biz_type'       => 'PRODUCTION_OUTPUT',
                'biz_order_type' => 'BOM',
                'biz_order_id'   => $bom->id,
                'stock_owner_id' => $owner->id,
                'location_id'    => $outputLocId,
                'sku_id'         => $bom->output_sku_id,
                'qty_change'     => $totalOutputQty,
                'direction'      => 'IN',
                'occurred_at'    => $occurredAt,
                'operator_id'    => $operatorId,
                'meta_json'      => array_merge($extraMeta, [
                    'bom_id'          => $bom->id,
                    'batch_qty'       => $batchQty,
                    'consume_txn_ids' => $consumeTxnIds,
                ]),
            ]);

            $outputBalance->available_qty = bcadd((string) $outputBalance->available_qty, $totalOutputQty, 4);
            $outputBalance->version      += 1;
            $outputBalance->save();

            // 6h: 事务提交后发 N+1 个 StockChanged 事件
            DB::afterCommit(function () use (
                $tenantId, $owner, $sourceLocId, $outputLocId,
                $bom, $consumePlans, $consumeTxnIds, $outputTxn
            ) {
                // 1 PRODUCTION_OUTPUT event
                StockChanged::dispatch(
                    $tenantId,
                    $owner->id,
                    $outputLocId,
                    $bom->output_sku_id,
                    (int) $outputTxn->id,
                    'PRODUCTION_OUTPUT'
                );

                // N PRODUCTION_CONSUME events
                foreach ($consumePlans as $i => $plan) {
                    StockChanged::dispatch(
                        $tenantId,
                        $owner->id,
                        $sourceLocId,
                        $plan['sku_id'],
                        $consumeTxnIds[$i],
                        'PRODUCTION_CONSUME'
                    );
                }
            });

            // 6i: return result
            return [
                'consume_txn_ids' => $consumeTxnIds,
                'output_txn_id'   => (int) $outputTxn->id,
            ];
        });
    }
}
