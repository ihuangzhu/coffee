<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Data\LoginData;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * 登录：按 (phone, password) 校验签发 Sanctum token。
 *
 * - phone 全局唯一（无租户维度）
 * - 失败统一抛"账号或密码错误"防账号枚举
 * - 成功更新 last_login_at 并签发 personal access token
 */
class LoginAction
{
    /**
     * @return array{token: string, user: User}
     *
     * @throws ValidationException 账号或密码错误（统一信息防枚举）
     */
    public function execute(LoginData $data): array
    {
        $user = User::query()
            ->where('phone', $data->phone)
            ->where('status', 'active')
            ->first();

        if (! $user || ! Hash::check($data->password, $user->password)) {
            throw ValidationException::withMessages(['phone' => '账号或密码错误']);
        }

        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('api')->plainTextToken;

        return ['token' => $token, 'user' => $user];
    }
}
