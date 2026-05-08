<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Http\Requests;

use App\Modules\Authorization\Rules\ValidPlatformPermissionsRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePlatformRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:60', 'unique:platform_roles,code'],
            'permissions' => ['required', 'array', new ValidPlatformPermissionsRule],
        ];
    }
}
