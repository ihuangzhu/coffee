<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Web;

use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Web/Inertia 登录登出控制器（session 模式）。
 *
 * 与 /api/auth/login(token 模式)分离：本控制器服务于浏览器表单提交场景。
 */
class AuthController extends Controller
{
    public function tenantLogin(Request $request): RedirectResponse
    {
        $user = $this->authenticate($request);

        Auth::guard('web')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);

        $this->autoSelectTenant($user, $request);

        return redirect()->intended('/tenant/dashboard');
    }

    public function tenantLogout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    public function platformLogin(Request $request): RedirectResponse
    {
        $user = $this->authenticate($request);

        if (! $user->is_platform_admin) {
            throw ValidationException::withMessages([
                'phone' => '该账号不是平台员工',
            ]);
        }

        Auth::guard('web')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);

        return redirect()->intended('/platform/dashboard');
    }

    public function platformLogout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/platform/login');
    }

    /**
     * 校验 phone + password；失败统一抛"账号或密码错误"防枚举。
     */
    private function authenticate(Request $request): User
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('phone', $data['phone'])
            ->where('status', 'active')
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['phone' => '账号或密码错误']);
        }

        return $user;
    }

    /**
     * 租户登录后自动选定一个 tenant，避免 tenant.current 为空导致整个后台空数据。
     * - 平台员工：跳过（他们走 /platform/tenants/{id}/enter 显式选）
     * - 普通用户：取首条 active membership 对应租户写 session
     * - 无 membership：不写 session，前端 tenant.current 为 null
     */
    private function autoSelectTenant(User $user, Request $request): void
    {
        if ($user->is_platform_admin) {
            return;
        }

        $membership = Membership::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->orderBy('joined_at')
            ->first();

        if (! $membership) {
            return;
        }

        $tenant = Tenant::query()->whereKey($membership->tenant_id)->first(['id', 'name']);
        if (! $tenant) {
            return;
        }

        $request->session()->put('current_tenant_id', $tenant->id);
        $request->session()->put('current_tenant_name', $tenant->name);
    }
}
