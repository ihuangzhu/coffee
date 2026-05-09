<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers\Web;

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Inventory\Models\StockTxn;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 租户后台 - 库存/流水查询。
 * /tenant/stock        库存余额（按 sku 聚合 4 状态桶）
 * /tenant/stock/txns   流水时间线
 */
class TenantStockController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $this->requireCurrentTenant($request);

        $storeId = (string) $request->query('store_id', '');
        $q = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));
        $pageSize = min(100, max(5, (int) $request->query('per_page', 20)));

        $stores = Store::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->orderBy('name')
            ->get(['id', 'name'])->toArray();

        $rows = [];
        $total = 0;
        if ($storeId !== '') {
            $owner = StockOwner::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('owner_type', 'STORE')
                ->where('owner_ref_id', $storeId)
                ->first();

            if ($owner) {
                $query = StockBalance::query()->withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('stock_owner_id', $owner->id)
                    ->orderByDesc('updated_at');

                if ($q !== '') {
                    $skuIds = ItemSku::query()->withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->whereHas('item', fn ($q2) => $q2->where('item_name', 'like', "%{$q}%"))
                        ->pluck('id');
                    $query->whereIn('sku_id', $skuIds);
                }

                $total = (clone $query)->count();
                $balances = $query->skip(($page - 1) * $pageSize)->take($pageSize)->get();

                $skuIds = $balances->pluck('sku_id')->all();
                $skus = ItemSku::query()->withoutGlobalScopes()
                    ->whereIn('id', $skuIds)->with('item:id,item_name,unit')
                    ->get()->keyBy('id');

                $rows = $balances->map(function (StockBalance $b) use ($skus) {
                    $sku = $skus->get($b->sku_id);
                    return [
                        'id' => $b->id,
                        'sku_id' => $b->sku_id,
                        'item_name' => $sku?->item?->item_name ?? '?',
                        'unit' => $sku?->item?->unit ?? '',
                        'barcode' => $sku?->barcode,
                        'available_qty' => (float) $b->available_qty,
                        'reserved_qty' => (float) $b->reserved_qty,
                        'in_transit_qty' => (float) $b->in_transit_qty,
                        'damaged_qty' => (float) $b->damaged_qty,
                        'updated_at' => $b->updated_at?->toIso8601String(),
                    ];
                })->all();
            }
        }

        return Inertia::render('tenant/Stock/Index', [
            'stores' => $stores,
            'store_id' => $storeId,
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'q' => $q,
        ]);
    }

    public function txns(Request $request): Response
    {
        $tenantId = $this->requireCurrentTenant($request);

        $bizType = (string) $request->query('biz_type', 'all');
        $skuId = (string) $request->query('sku_id', '');
        $page = max(1, (int) $request->query('page', 1));
        $pageSize = min(100, max(5, (int) $request->query('per_page', 30)));

        $query = StockTxn::query()->where('tenant_id', $tenantId)
            ->orderByDesc('id');
        if ($bizType !== 'all') {
            $query->where('biz_type', $bizType);
        }
        if ($skuId !== '') {
            $query->where('sku_id', $skuId);
        }

        $total = (clone $query)->count();
        $txns = $query->skip(($page - 1) * $pageSize)->take($pageSize)->get();

        $skuIds = $txns->pluck('sku_id')->unique();
        $skus = ItemSku::query()->withoutGlobalScopes()
            ->whereIn('id', $skuIds)->with('item:id,item_name')
            ->get()->keyBy('id');

        // Retrieve IDs of txns that have been cancelled (i.e. another txn points at them).
        // Use whereRaw for SQLite compatibility (whereNotNull on JSON path is unreliable on SQLite).
        $cancelledIds = StockTxn::query()
            ->where('tenant_id', $tenantId)
            ->whereRaw("json_extract(meta_json, '$.cancels_txn_id') IS NOT NULL")
            ->pluck('meta_json->cancels_txn_id')->all();
        $cancelledIdSet = array_flip($cancelledIds);

        $rows = $txns->map(function (StockTxn $t) use ($skus, $cancelledIdSet) {
            $sku = $skus->get($t->sku_id);
            return [
                'id' => (int) $t->id,
                'biz_type' => $t->biz_type->value,
                'direction' => $t->direction->value,
                'qty_change' => (float) $t->qty_change,
                'sku_id' => $t->sku_id,
                'item_name' => $sku?->item?->item_name ?? '?',
                'occurred_at' => $t->occurred_at?->toIso8601String(),
                'unit_cost_cents' => $t->unit_cost_cents,
                'amount_cents' => $t->amount_cents,
                'meta' => $t->meta_json,
                'is_cancelled' => isset($cancelledIdSet[(int) $t->id]),
                'is_reversal' => isset($t->meta_json['cancels_txn_id']),
            ];
        })->all();

        return Inertia::render('tenant/Stock/Txns', [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'biz_type' => $bizType,
            'sku_id' => $skuId,
            'biz_types' => array_column(\App\Modules\Inventory\Enums\StockTxnBizType::cases(), 'value'),
        ]);
    }

    private function requireCurrentTenant(Request $request): string
    {
        $id = $request->session()->get('current_tenant_id');
        if (! $id) {
            throw ValidationException::withMessages(['tenant' => '尚未选定租户']);
        }
        return (string) $id;
    }
}
