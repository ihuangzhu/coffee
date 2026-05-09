<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers\Web;

use App\Modules\Inventory\Actions\ProduceAction;
use App\Modules\Inventory\Models\Bom;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Tenancy\Models\Store;
use App\Support\Eloquent\TenantScope;
use App\Support\Exceptions\BusinessException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TenantProduceController extends Controller
{
    public function create(Request $request): Response
    {
        $tenantId = $this->requireCurrentTenant($request);

        return Inertia::render('tenant/Produce/Index', [
            'stores' => Store::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->get(['id', 'name']),
            'boms' => Bom::query()->withoutGlobalScopes([TenantScope::class])
                ->with('outputSku.item')
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->get(['id', 'output_sku_id', 'output_qty', 'bom_type', 'store_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        // Tenant-scoped Rule::exists for both store_id and bom_id keeps the validation contract
        // symmetric: cross-tenant ids → 422 with field-level errors. Inertia surfaces these inline.
        $data = $request->validate([
            'store_id' => ['required', 'string', 'size:26',
                Rule::exists('stores', 'id')->where('tenant_id', $tenantId)],
            'bom_id' => ['required', 'string', 'size:26',
                Rule::exists('boms', 'id')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')],
            'batch_qty' => ['required', 'numeric', 'gt:0'],
            'source_location_id' => ['nullable', 'string', 'size:26'],
            'output_location_id' => ['nullable', 'string', 'size:26'],
        ]);

        try {
            ProduceAction::handle(
                $tenantId,
                $data['store_id'],
                $data['bom_id'],
                (string) $data['batch_qty'],
                (string) $request->user()->id,
                $data['source_location_id'] ?? null,
                $data['output_location_id'] ?? null,
            );
        } catch (BusinessException $e) {
            // Convert to ValidationException so Inertia re-renders the form with inline field errors
            // (instead of `abort()` which would render a bare error page with no form context).
            $field = match ($e->errorCode()) {
                'BOM_NOT_FOUND', 'BOM_STORE_MISMATCH', 'BOM_NO_COMPONENTS',
                'INVENTORY_DISABLED' => 'bom_id',
                'INSUFFICIENT_STOCK', 'INVALID_BATCH_QTY' => 'batch_qty',
                default => 'bom_id',
            };
            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }

        return redirect('/tenant/produce')->with('success', '生产入库已完成');
    }

    public function preview(Request $request): JsonResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        // Tenant-scoped Rule::exists on bom_id: cross-tenant bom_id → 422 validation error
        // (consistent with the store() endpoint contract).
        $data = $request->validate([
            'store_id' => ['required', 'string', 'size:26',
                Rule::exists('stores', 'id')->where('tenant_id', $tenantId)],
            'bom_id' => ['required', 'string', 'size:26',
                Rule::exists('boms', 'id')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')],
            'batch_qty' => ['required', 'numeric', 'gt:0'],
        ]);

        $bom = Bom::query()->withoutGlobalScopes([TenantScope::class])
            ->with(['outputSku.item', 'components.componentSku.item'])
            ->where('tenant_id', $tenantId)
            ->where('id', $data['bom_id'])
            ->whereNull('deleted_at')
            ->firstOrFail();

        $owner = StockOwner::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('owner_type', 'STORE')
            ->where('owner_ref_id', $data['store_id'])
            ->firstOrFail();

        $defaultLoc = StockLocation::query()->withoutGlobalScopes()
            ->where('stock_owner_id', $owner->id)
            ->where('location_code', 'DEFAULT')
            ->firstOrFail();

        $batchQty = (string) $data['batch_qty'];
        $totalOutput = bcmul((string) $bom->output_qty, $batchQty, 4);

        $consumes = [];
        foreach ($bom->components as $c) {
            $needed = bcmul(
                bcmul((string) $c->consume_qty, bcadd('1', (string) $c->loss_rate, 4), 4),
                $batchQty,
                4
            );

            $balance = StockBalance::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('stock_owner_id', $owner->id)
                ->where('location_id', $defaultLoc->id)
                ->where('sku_id', $c->component_sku_id)
                ->first();

            $available = (string) ($balance?->available_qty ?? '0');

            $consumes[] = [
                'sku_id'    => $c->component_sku_id,
                'sku_name'  => $c->componentSku->item->name . ' / ' . $c->componentSku->spec_name,
                'needed'    => $needed,
                'available' => $available,
                'sufficient' => bccomp($available, $needed, 4) >= 0,
            ];
        }

        return response()->json([
            'output' => [
                'sku_id'   => $bom->output_sku_id,
                'sku_name' => $bom->outputSku->item->name . ' / ' . $bom->outputSku->spec_name,
                'qty'      => $totalOutput,
            ],
            'consumes' => $consumes,
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
