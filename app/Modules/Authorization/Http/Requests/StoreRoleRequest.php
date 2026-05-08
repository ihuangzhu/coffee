<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Http\Requests;

use App\Modules\Authorization\Rules\ValidPermissionsRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:60'],
            'scope' => ['required', 'in:tenant,store'],
            'permissions' => ['required', 'array', new ValidPermissionsRule],
        ];
    }
}
