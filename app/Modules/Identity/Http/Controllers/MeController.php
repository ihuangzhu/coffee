<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Models\UserOrgRel;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * 当前用户身份与租户列表查询。
 *
 * 路由仅 auth:sanctum，**不挂 tenant**——这是查"我有哪些租户"的端点，
 * 调用时前端尚未选定 X-Tenant-Id。
 */
class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'phone' => $user->phone,
            'name' => $user->name,
            'is_platform_admin' => (bool) $user->is_platform_admin,
        ]);
    }

    public function memberships(Request $request): JsonResponse
    {
        $user = $request->user();

        // 跨租户查询自身所有绑定：必须 withoutGlobalScopes 绕过 BelongsToTenant 全局过滤，
        // 否则受 CurrentTenant 限制只能看到当前租户。
        $memberships = UserOrgRel::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->get();

        // 一次性取租户名（避免 N+1）
        $tenantIds = $memberships->pluck('tenant_id')->all();
        $tenants = Tenant::query()
            ->whereIn('id', $tenantIds)
            ->get()
            ->keyBy('id');

        $list = $memberships->map(function (UserOrgRel $m) use ($tenants) {
            $tenant = $tenants->get($m->tenant_id);

            return [
                'tenant_id' => $m->tenant_id,
                'tenant_name' => $tenant?->name,
                'store_id' => $m->store_id,
                'joined_at' => optional($m->joined_at)->toIso8601String(),
            ];
        })->values();

        return response()->json(['memberships' => $list]);
    }
}
