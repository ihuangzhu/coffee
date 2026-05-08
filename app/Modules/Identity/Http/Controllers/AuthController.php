<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Actions\LoginAction;
use App\Modules\Identity\Data\LoginData;
use App\Modules\Identity\Http\Requests\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * 登录与登出端点。
 *
 * 响应仅含 token + 用户基本身份；租户列表请前端调 GET /api/me/memberships。
 */
class AuthController extends Controller
{
    public function login(LoginRequest $request, LoginAction $action): JsonResponse
    {
        $result = $action->execute(LoginData::from($request->validated()));
        $user = $result['user'];

        return response()->json([
            'token' => $result['token'],
            'user' => [
                'id' => $user->id,
                'phone' => $user->phone,
                'name' => $user->name,
                'is_platform_admin' => (bool) $user->is_platform_admin,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['ok' => true]);
    }
}
