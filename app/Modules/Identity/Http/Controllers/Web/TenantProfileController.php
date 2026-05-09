<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Web;

use App\Modules\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 租户后台个人资料：当前用户改 name / phone / password。
 */
class TenantProfileController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('tenant/Profile/Edit');
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'phone' => ['required', 'string', 'max:20',
                Rule::unique('users', 'phone')->ignore($user->id),
            ],
        ]);

        $user->update($data);

        return back()->with('success', '已保存');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => '当前密码不正确',
            ]);
        }

        $user->update(['password' => $data['password']]);

        return back()->with('success', '密码已更新');
    }
}
