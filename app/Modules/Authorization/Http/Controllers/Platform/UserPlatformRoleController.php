<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Http\Controllers\Platform;

use App\Modules\Authorization\Models\PlatformRole;
use App\Modules\Identity\Models\User;
use App\Support\Exceptions\BusinessException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class UserPlatformRoleController extends Controller
{
    public function update(Request $request, string $userId): JsonResponse
    {
        $data = $request->validate([
            'platform_role_id' => ['nullable', 'string', 'size:26'],
        ]);

        $target = User::query()->findOrFail($userId);
        if (! $target->is_platform_admin) {
            throw new BusinessException(
                'platform-role.target-not-admin',
                '目标用户不是平台员工',
                422,
            );
        }

        if ($data['platform_role_id']) {
            // 验存在
            PlatformRole::query()->findOrFail($data['platform_role_id']);
        }

        $target->update(['platform_role_id' => $data['platform_role_id']]);

        return response()->json(['user' => $target->only(['id', 'name', 'platform_role_id'])]);
    }
}
