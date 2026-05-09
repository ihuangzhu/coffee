<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Tenancy\Models\Tenant;
use App\Support\Authorization\WebPermissions;
use App\Support\Tenancy\WebTenantAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

/**
 * Inertia 请求处理中间件：
 * - 指定 Inertia 根 Blade 视图为 'app'
 * - 注入全局 share props，供 PlatformLayout / TenantLayout / 各 page 消费
 *
 * 当前阶段所有 share 字段都是 stub（前端视图迁移期），后续接真实数据时
 * 把每个字段从静态值替换为 model 查询即可，前端 schema 不变。
 */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => function () {
                    $u = Auth::guard('web')->user();
                    if (! $u) {
                        return null;
                    }
                    return [
                        'id' => $u->id,
                        'name' => $u->name,
                        'phone' => $u->phone,
                        'is_platform_admin' => (bool) $u->is_platform_admin,
                    ];
                },
            ],
            'tenant' => [
                'current' => function () use ($request) {
                    $id = $request->session()->get('current_tenant_id');
                    if (! $id) {
                        return null;
                    }
                    $t = Tenant::query()->whereKey($id)->first(['id', 'name']);
                    return $t ? ['id' => $t->id, 'name' => $t->name] : null;
                },
                'available' => function () {
                    $u = Auth::guard('web')->user();
                    return $u ? WebTenantAccess::availableTenants($u) : [];
                },
            ],
            'store' => [
                'current' => function () use ($request) {
                    $id = $request->session()->get('current_store_id');
                    if (! $id) {
                        return null;
                    }
                    return [
                        'id' => $id,
                        'name' => $request->session()->get('current_store_name', ''),
                    ];
                },
                'available' => function () use ($request) {
                    $u = Auth::guard('web')->user();
                    $tenantId = $request->session()->get('current_tenant_id');
                    if (! $u || ! $tenantId) {
                        return [];
                    }
                    return WebTenantAccess::availableStores($u, $tenantId);
                },
            ],
            'permissions' => function () use ($request) {
                $user = Auth::guard('web')->user();
                $tenantId = $request->session()->get('current_tenant_id');
                return WebPermissions::resolve($user, $tenantId ? (string) $tenantId : null);
            },
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
            ],
            'app' => [
                'name' => config('app.name'),
                'env' => config('app.env'),
                'health' => [
                    'uptime' => '99.97%',
                    'p95' => '142ms',
                    'tone' => 'ok',
                ],
            ],
            'notifications' => [
                'unread' => 0,
                'recent' => [],
            ],
        ];
    }
}
