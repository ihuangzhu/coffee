<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Tenancy\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * 当前租户详情查询。
 *
 * 客户端在 TenantMiddleware 通过后调本端点，确认 X-Tenant-Id 已正确解析。
 */
class TenantController extends Controller
{
    public function current(): JsonResponse
    {
        $id = app(CurrentTenant::class)->require();
        $tenant = Tenant::query()->whereKey($id)->firstOrFail();

        return response()->json([
            'id' => $tenant->id,
            'name' => $tenant->name,
            'status' => $tenant->status->value,
        ]);
    }
}
