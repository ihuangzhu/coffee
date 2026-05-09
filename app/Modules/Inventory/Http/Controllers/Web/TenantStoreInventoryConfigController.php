<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers\Web;

use App\Modules\Tenancy\Models\Store;
use App\Modules\Inventory\Models\StoreInventoryConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TenantStoreInventoryConfigController extends Controller
{
    public function show(Request $request, string $storeId): Response
    {
        $tenantId = $this->requireCurrentTenant($request);
        $store = Store::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->whereKey($storeId)->firstOrFail();
        $cfg = StoreInventoryConfig::query()->firstOrCreate(
            ['store_id' => $storeId],
            ['tenant_id' => $tenantId],
        );

        return Inertia::render('tenant/Stores/InventoryConfig', [
            'store' => ['id' => $store->id, 'name' => $store->name],
            'config' => [
                'inventory_enabled' => (bool) $cfg->inventory_enabled,
                'multi_location_enabled' => (bool) $cfg->multi_location_enabled,
                'default_stock_mode' => $cfg->default_stock_mode,
                'production_enabled' => (bool) $cfg->production_enabled,
                'allow_direct_stock_adjustment' => (bool) $cfg->allow_direct_stock_adjustment,
            ],
        ]);
    }

    public function update(Request $request, string $storeId): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        Store::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->whereKey($storeId)->firstOrFail();

        $data = $request->validate([
            'inventory_enabled' => ['required', 'boolean'],
            'multi_location_enabled' => ['required', 'boolean'],
            'default_stock_mode' => ['required', 'string', 'max:20'],
            'production_enabled' => ['required', 'boolean'],
            'allow_direct_stock_adjustment' => ['required', 'boolean'],
        ]);

        StoreInventoryConfig::query()->where('store_id', $storeId)->update($data);

        return back()->with('success', '门店配置已保存');
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
