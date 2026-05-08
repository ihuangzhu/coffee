<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Http\Controllers;

use App\Modules\Authorization\Http\Requests\CreateRoleBindingRequest;
use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\Models\UserRoleBinding;
use App\Modules\Tenancy\Models\Store;
use App\Support\Exceptions\BusinessException;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class RoleBindingController extends Controller
{
    public function store(CreateRoleBindingRequest $request, string $userId): JsonResponse
    {
        $tenantId = app(CurrentTenant::class)->require();
        $roleId = $request->input('role_id');
        $storeId = $request->input('store_id');

        // 1. role 必须属于本租户或全局模板
        $role = Role::query()
            ->where('id', $roleId)
            ->where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id'))
            ->first();
        if (! $role) {
            abort(404);
        }

        // 2. scope ↔ store_id 校验
        if ($role->scope === 'store' && ! $storeId) {
            throw new BusinessException('binding.store-id-required', 'store-scope role 必须传 store_id', 422);
        }
        if ($role->scope === 'tenant' && $storeId) {
            throw new BusinessException('binding.store-id-not-allowed', 'tenant-scope role 不可传 store_id', 422);
        }

        // 3. store 必须属于本租户
        if ($storeId) {
            $store = Store::query()->withoutGlobalScopes()
                ->where('id', $storeId)->where('tenant_id', $tenantId)->first();
            if (! $store) {
                throw new BusinessException('binding.store-not-in-tenant', 'store_id 不属于当前租户', 422);
            }
        }

        // 4. 重复 / revoked 检查
        return DB::transaction(function () use ($userId, $roleId, $tenantId, $storeId, $request) {
            $existingQuery = UserRoleBinding::query()->withoutGlobalScopes()
                ->where('user_id', $userId)
                ->where('role_id', $roleId)
                ->where('tenant_id', $tenantId);
            $existingQuery = $storeId
                ? $existingQuery->where('store_id', $storeId)
                : $existingQuery->whereNull('store_id');

            $existing = $existingQuery->first();

            if ($existing && $existing->status === 'active') {
                return response()->json(['binding' => $existing], 200);
            }

            if ($existing && $existing->status === 'revoked') {
                $existing->update([
                    'status' => 'active',
                    'granted_by' => $request->user()->id,
                    'granted_at' => now(),
                ]);
                return response()->json(['binding' => $existing], 200);
            }

            $binding = UserRoleBinding::query()->withoutGlobalScopes()->create([
                'user_id' => $userId,
                'role_id' => $roleId,
                'tenant_id' => $tenantId,
                'store_id' => $storeId,
                'status' => 'active',
                'granted_by' => $request->user()->id,
                'granted_at' => now(),
            ]);

            return response()->json(['binding' => $binding], 201);
        });
    }

    public function destroy(string $bindingId): JsonResponse
    {
        $tenantId = app(CurrentTenant::class)->require();

        $binding = UserRoleBinding::query()->withoutGlobalScopes()->where('id', $bindingId)->first();
        if (! $binding || $binding->tenant_id !== $tenantId) {
            abort(404);
        }

        $binding->update(['status' => 'revoked']);
        return response()->json(null, 204);
    }
}
