<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers\Web;

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Inventory\Actions\AdjustStockAction;
use App\Modules\Inventory\Actions\DamageAction;
use App\Modules\Inventory\Actions\ReverseStockTxnAction;
use App\Modules\Inventory\Actions\StocktakeAction;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockOwner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 三件套写入入口 + 撤销流水入口。
 * 所有写入都通过对应 Action（已含 InventoryGuard 校验、行级锁、事务）。
 */
class TenantStockMutationController extends Controller
{
    public function adjustForm(Request $request): Response
    {
        return $this->renderForm($request, 'tenant/Stock/Adjust');
    }

    public function stocktakeForm(Request $request): Response
    {
        return $this->renderForm($request, 'tenant/Stock/Stocktake');
    }

    public function damageForm(Request $request): Response
    {
        return $this->renderForm($request, 'tenant/Stock/Damage');
    }

    public function adjust(Request $request): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        $data = $request->validate([
            'store_id' => ['required', 'string', 'size:26'],
            'sku_id' => ['required', 'string', 'size:26'],
            'qty_change' => ['required', 'numeric'],
            'direction' => ['required', Rule::in(['IN', 'OUT'])],
            'subtype' => ['required', Rule::in(['INITIAL', 'MANUAL'])],
        ]);

        [$ownerId, $locationId] = $this->resolveOwnerLocation($tenantId, $data['store_id']);
        AdjustStockAction::handle(
            $tenantId, $data['store_id'], $ownerId, $locationId, $data['sku_id'],
            (string) $data['qty_change'], $data['direction'], $data['subtype'],
            (string) $request->user()->id,
        );

        return redirect('/tenant/stock?store_id='.$data['store_id'])
            ->with('success', '调整已记录');
    }

    public function stocktake(Request $request): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        $data = $request->validate([
            'store_id' => ['required', 'string', 'size:26'],
            'sku_id' => ['required', 'string', 'size:26'],
            'actual_qty' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        [$ownerId, $locationId] = $this->resolveOwnerLocation($tenantId, $data['store_id']);
        StocktakeAction::handle(
            $tenantId, $data['store_id'], $ownerId, $locationId, $data['sku_id'],
            (string) $data['actual_qty'], (string) $request->user()->id, $data['note'] ?? null,
        );

        return redirect('/tenant/stock?store_id='.$data['store_id'])
            ->with('success', '盘点已记录');
    }

    public function damage(Request $request): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        $data = $request->validate([
            'store_id' => ['required', 'string', 'size:26'],
            'sku_id' => ['required', 'string', 'size:26'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'unit_cost_cents' => ['required', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        [$ownerId, $locationId] = $this->resolveOwnerLocation($tenantId, $data['store_id']);
        DamageAction::handle(
            $tenantId, $data['store_id'], $ownerId, $locationId, $data['sku_id'],
            (string) $data['qty'], (int) $data['unit_cost_cents'],
            (string) $request->user()->id, $data['reason'] ?? null,
        );

        return redirect('/tenant/stock?store_id='.$data['store_id'])
            ->with('success', '报损已记录');
    }

    public function reverse(Request $request, int $id): RedirectResponse
    {
        $this->requireCurrentTenant($request);
        ReverseStockTxnAction::handle($id, (string) $request->user()->id);
        return back()->with('success', '已撤销');
    }

    private function renderForm(Request $request, string $page): Response
    {
        $tenantId = $this->requireCurrentTenant($request);
        $stores = Store::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->orderBy('name')
            ->get(['id', 'name'])->toArray();

        $skus = ItemSku::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('inventory_enabled', true)
            ->with('item:id,item_name,unit')
            ->limit(500)->get()
            ->map(fn (ItemSku $s) => [
                'id' => $s->id,
                'item_name' => $s->item?->item_name ?? '?',
                'unit' => $s->item?->unit ?? '',
                'barcode' => $s->barcode,
            ])->all();

        return Inertia::render($page, [
            'stores' => $stores,
            'skus' => $skus,
        ]);
    }

    private function resolveOwnerLocation(string $tenantId, string $storeId): array
    {
        $owner = StockOwner::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('owner_type', 'STORE')->where('owner_ref_id', $storeId)
            ->firstOrFail();
        $location = StockLocation::query()->withoutGlobalScopes()
            ->where('stock_owner_id', $owner->id)
            ->where('location_code', 'DEFAULT')
            ->firstOrFail();
        return [$owner->id, $location->id];
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
