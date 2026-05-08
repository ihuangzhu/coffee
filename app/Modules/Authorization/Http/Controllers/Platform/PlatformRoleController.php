<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Http\Controllers\Platform;

use App\Modules\Authorization\Http\Requests\StorePlatformRoleRequest;
use App\Modules\Authorization\Http\Requests\UpdatePlatformRoleRequest;
use App\Modules\Authorization\Models\PlatformRole;
use App\Modules\Identity\Models\User;
use App\Support\Exceptions\BusinessException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class PlatformRoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = PlatformRole::query()->orderBy('is_system', 'desc')->orderBy('name')->get();

        return response()->json(['roles' => $roles]);
    }

    public function store(StorePlatformRoleRequest $request): JsonResponse
    {
        $role = PlatformRole::query()->create($request->validated() + ['is_system' => false]);

        return response()->json(['role' => $role], 201);
    }

    public function update(UpdatePlatformRoleRequest $request, string $id): JsonResponse
    {
        $role = PlatformRole::query()->findOrFail($id);
        if ($role->is_system) {
            throw new BusinessException('role.system-locked', '系统内置角色不可修改', 403);
        }
        $role->fill($request->validated())->save();

        return response()->json(['role' => $role]);
    }

    public function destroy(string $id): JsonResponse
    {
        $role = PlatformRole::query()->findOrFail($id);
        if ($role->is_system) {
            throw new BusinessException('role.system-locked', '系统内置角色不可删除', 403);
        }
        $userCount = User::query()->where('platform_role_id', $id)->count();
        if ($userCount > 0) {
            throw new BusinessException('role.in-use', '角色被用户引用，无法删除', 409, ['user_count' => $userCount]);
        }
        $role->delete();

        return response()->json(null, 204);
    }
}
