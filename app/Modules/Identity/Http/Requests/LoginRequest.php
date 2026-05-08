<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 登录请求格式校验。
 *
 * 不做账号存在性校验：统一由 Action 抛"账号或密码错误"防账号枚举。
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:6'],
        ];
    }
}
