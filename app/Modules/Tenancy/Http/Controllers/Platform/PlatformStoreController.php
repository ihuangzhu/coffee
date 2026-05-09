<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers\Platform;

use App\Modules\Tenancy\Enums\StoreStatus;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class PlatformStoreController extends Controller
{
    public function index(Request $request, string $tenantId): Response
    {
        $tenant = Tenant::query()->whereKey($tenantId)->firstOrFail();

        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');
        $page = max(1, (int) $request->query('page', 1));
        $pageSize = min(100, max(5, (int) $request->query('per_page', 20)));

        $query = Store::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('created_at');
        if ($q !== '') {
            $query->where('name', 'like', "%{$q}%");
        }
        if (in_array($status, ['active', 'disabled'], true)) {
            $query->where('status', $status);
        }

        $total = (clone $query)->count();
        $rows = $query
            ->skip(($page - 1) * $pageSize)
            ->take($pageSize)
            ->get(['id', 'name', 'status', 'created_at'])
            ->map(fn (Store $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'status' => $s->status->value,
                'created_at' => $s->created_at?->toIso8601String(),
            ])->all();

        return Inertia::render('platform/Stores/Index', [
            'tenant' => ['id' => $tenant->id, 'name' => $tenant->name],
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'q' => $q,
            'status' => $status,
        ]);
    }

    public function create(string $tenantId): Response
    {
        $tenant = Tenant::query()->whereKey($tenantId)->firstOrFail();

        return Inertia::render('platform/Stores/Create', [
            'tenant' => ['id' => $tenant->id, 'name' => $tenant->name],
        ]);
    }

    public function edit(string $tenantId, string $storeId): Response
    {
        $tenant = Tenant::query()->whereKey($tenantId)->firstOrFail();
        $store = Store::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereKey($storeId)
            ->firstOrFail();

        return Inertia::render('platform/Stores/Edit', [
            'tenant' => ['id' => $tenant->id, 'name' => $tenant->name],
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'status' => $store->status->value,
            ],
        ]);
    }

    public function update(Request $request, string $tenantId, string $storeId): RedirectResponse
    {
        $tenant = Tenant::query()->whereKey($tenantId)->firstOrFail();
        $store = Store::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereKey($storeId)
            ->firstOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'status' => ['required', 'in:active,disabled'],
        ]);

        $store->update([
            'name' => $data['name'],
            'status' => StoreStatus::from($data['status']),
        ]);

        return back()->with('success', '已更新');
    }

    /**
     * Inertia 表单提交：新建门店（status 强制 active）。
     */
    public function storeFromForm(Request $request, string $tenantId): RedirectResponse
    {
        $tenant = Tenant::query()->whereKey($tenantId)->firstOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        Store::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => $data['name'],
            'status' => 'active',
        ]);

        return redirect("/platform/tenants/{$tenant->id}/stores")->with('success', '门店已创建');
    }

    /**
     * 旧 JSON API（保留给 /api/platform/tenants/{tenantId}/stores）。
     */
    public function store(Request $request, string $tenantId): JsonResponse
    {
        $tenant = Tenant::query()->whereKey($tenantId)->firstOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $store = Store::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => $data['name'],
            'status' => 'active',
        ]);

        return response()->json([
            'id' => $store->id,
            'tenant_id' => $store->tenant_id,
            'name' => $store->name,
            'status' => $store->status->value,
        ], 201);
    }
}
