<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers\Web;

use App\Modules\Tenancy\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 租户后台门店只读列表（路径 /tenant/stores）。
 * 写操作走平台后台 PlatformStoreController。
 */
class TenantStoreController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $this->requireCurrentTenant($request);

        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');
        $page = max(1, (int) $request->query('page', 1));
        $pageSize = min(100, max(5, (int) $request->query('per_page', 20)));

        $query = Store::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
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

        return Inertia::render('tenant/Stores/Index', [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'q' => $q,
            'status' => $status,
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
