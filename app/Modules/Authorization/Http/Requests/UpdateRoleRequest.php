<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Http\Requests;

use App\Modules\Authorization\Rules\ValidPermissionsRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'permissions' => ['sometimes', 'array', new ValidPermissionsRule],
        ];
    }
}
