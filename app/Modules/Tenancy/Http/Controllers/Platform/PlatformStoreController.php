<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers\Platform;

use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PlatformStoreController extends Controller
{
    public function store(Request $request, string $tenantId): JsonResponse
    {
        $tenant = Tenant::query()->whereKey($tenantId)->firstOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        // 平台员工创建：必须显式指定 tenant_id（不依赖 CurrentTenant，本路由不挂 tenant 中间件）
        // BelongsToTenant trait 的 creating 钩子在 tenant_id 已显式赋值时不会触发 require()
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
