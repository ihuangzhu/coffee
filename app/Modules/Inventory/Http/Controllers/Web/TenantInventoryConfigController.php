<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers\Web;

use App\Modules\Inventory\Models\TenantInventoryConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TenantInventoryConfigController extends Controller
{
    public function show(Request $request): Response
    {
        $tenantId = $this->requireCurrentTenant($request);
        $cfg = TenantInventoryConfig::query()->firstOrCreate(['tenant_id' => $tenantId]);

        return Inertia::render('tenant/Settings/InventoryConfig', [
            'config' => [
                'inventory_enabled' => (bool) $cfg->inventory_enabled,
                'multi_location_enabled' => (bool) $cfg->multi_location_enabled,
                'production_enabled' => (bool) $cfg->production_enabled,
                'purchase_enabled' => (bool) $cfg->purchase_enabled,
                'transfer_enabled' => (bool) $cfg->transfer_enabled,
                'stocktaking_enabled' => (bool) $cfg->stocktaking_enabled,
                'negative_stock_allowed' => (bool) $cfg->negative_stock_allowed,
                'inventory_cost_method' => $cfg->inventory_cost_method->value,
                'expiry_management_enabled' => (bool) $cfg->expiry_management_enabled,
                'batch_management_enabled' => (bool) $cfg->batch_management_enabled,
                'auto_deduct_raw_material_enabled' => (bool) $cfg->auto_deduct_raw_material_enabled,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        $data = $request->validate([
            'inventory_enabled' => ['required', 'boolean'],
            'multi_location_enabled' => ['required', 'boolean'],
            'production_enabled' => ['required', 'boolean'],
            'purchase_enabled' => ['required', 'boolean'],
            'transfer_enabled' => ['required', 'boolean'],
            'stocktaking_enabled' => ['required', 'boolean'],
            'negative_stock_allowed' => ['required', 'boolean'],
            'inventory_cost_method' => ['required', Rule::in(['FIFO', 'MOVING_AVG', 'STANDARD'])],
            'expiry_management_enabled' => ['required', 'boolean'],
            'batch_management_enabled' => ['required', 'boolean'],
            'auto_deduct_raw_material_enabled' => ['required', 'boolean'],
        ]);

        TenantInventoryConfig::query()->where('tenant_id', $tenantId)->update($data);

        return back()->with('success', '配置已保存');
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
