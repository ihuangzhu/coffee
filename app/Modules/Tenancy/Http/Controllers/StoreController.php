<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Tenancy\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * 当前租户下的门店列表。
 *
 * Store 模型挂 BelongsToTenant，全局作用域自动按 CurrentTenant 过滤；
 * 此处 Store::query() 无需显式 where('tenant_id', ...)。
 */
class StoreController extends Controller
{
    public function index(): JsonResponse
    {
        // BelongsToTenant 全局作用域自动加 WHERE tenant_id = CurrentTenant
        $stores = Store::query()
            ->orderBy('created_at')
            ->get(['id', 'name', 'status']);

        return response()->json(['stores' => $stores]);
    }
}
