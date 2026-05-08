<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateRoleBindingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role_id' => ['required', 'string', 'size:26'],
            'store_id' => ['nullable', 'string', 'size:26'],
        ];
    }
}
