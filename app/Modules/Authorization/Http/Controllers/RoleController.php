<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Http\Controllers;

use App\Modules\Authorization\Models\Role;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = app(CurrentTenant::class)->require();

        // 本租户角色 + 全局模板（tenant_id IS NULL）
        $roles = Role::query()
            ->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            })
            ->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get(['id', 'tenant_id', 'name', 'code', 'scope', 'permissions', 'is_system']);

        return response()->json(['roles' => $roles]);
    }
}
