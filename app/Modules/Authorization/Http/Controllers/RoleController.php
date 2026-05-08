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

    public function store(\App\Modules\Authorization\Http\Requests\StoreRoleRequest $request): JsonResponse
    {
        $tenantId = app(CurrentTenant::class)->require();

        $role = Role::query()->create([
            'tenant_id' => $tenantId,
            'name' => $request->input('name'),
            'code' => $request->input('code'),
            'scope' => $request->input('scope'),
            'permissions' => $request->input('permissions'),
            'is_system' => false,
        ]);

        return response()->json(['role' => $role], 201);
    }

    public function update(
        \App\Modules\Authorization\Http\Requests\UpdateRoleRequest $request,
        string $roleId
    ): JsonResponse {
        $tenantId = app(CurrentTenant::class)->require();

        $role = Role::query()->where('id', $roleId)->first();
        if (! $role) {
            abort(404);
        }

        // 系统内置角色（tenant_id IS NULL）不可修改
        if ($role->is_system) {
            throw new \App\Support\Exceptions\BusinessException(
                'role.system-locked',
                '系统内置角色不可修改',
                403,
            );
        }

        // 别租户角色 → 隐藏存在性，返回 404
        if ($role->tenant_id !== $tenantId) {
            abort(404);
        }

        $role->fill($request->only(['name', 'permissions']))->save();

        return response()->json(['role' => $role]);
    }

    public function destroy(string $roleId): JsonResponse
    {
        $tenantId = app(CurrentTenant::class)->require();

        $role = Role::query()->where('id', $roleId)->first();
        if (! $role) {
            abort(404);
        }

        if ($role->is_system) {
            throw new \App\Support\Exceptions\BusinessException(
                'role.system-locked', '系统内置角色不可删除', 403,
            );
        }

        // 别租户角色 → 隐藏存在性，返回 404
        if ($role->tenant_id !== $tenantId) {
            abort(404);
        }

        $bindingCount = \App\Modules\Authorization\Models\UserRoleBinding::query()
            ->withoutGlobalScopes()
            ->where('role_id', $roleId)
            ->where('status', 'active')
            ->count();

        if ($bindingCount > 0) {
            throw new \App\Support\Exceptions\BusinessException(
                'role.in-use',
                '角色被在用绑定引用，无法删除',
                409,
                ['binding_count' => $bindingCount],
            );
        }

        // 删除 revoked 绑定留痕（FK restrictOnDelete，revoked 不算占用但需先清理）
        \Illuminate\Support\Facades\DB::transaction(function () use ($role, $roleId) {
            \App\Modules\Authorization\Models\UserRoleBinding::query()
                ->withoutGlobalScopes()
                ->where('role_id', $roleId)
                ->where('status', 'revoked')
                ->delete();

            $role->delete();
        });

        return response()->json(null, 204);
    }
}
