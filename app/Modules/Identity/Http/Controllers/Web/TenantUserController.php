<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Web;

use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\Models\UserRoleBinding;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 租户后台用户管理（成员/角色绑定，路径 /tenant/users）。
 * 当前租户从 session('current_tenant_id') 解析。
 */
class TenantUserController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $this->requireCurrentTenant($request);

        $q = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));
        $pageSize = min(100, max(5, (int) $request->query('per_page', 20)));

        // 当前租户活跃成员的去重 user_id（含 tenant-level 与 store-level membership）
        $userIdsQuery = Membership::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->select('user_id')->distinct();

        $userQuery = User::query()->whereIn('id', $userIdsQuery)
            ->orderByDesc('created_at');
        if ($q !== '') {
            $userQuery->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            });
        }

        $total = (clone $userQuery)->count();
        $users = $userQuery->skip(($page - 1) * $pageSize)->take($pageSize)
            ->get(['id', 'name', 'phone', 'created_at']);

        // 批量取 tenant-level 角色绑定（scope=tenant ⇔ store_id IS NULL）
        $userIds = $users->pluck('id')->all();
        $bindings = UserRoleBinding::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('user_id', $userIds)
            ->whereNull('store_id')
            ->where('status', 'active')
            ->get(['user_id', 'role_id'])
            ->keyBy('user_id');
        $roleNames = Role::query()->whereIn('id', $bindings->pluck('role_id')->all())
            ->pluck('name', 'id');

        $rows = $users->map(function (User $u) use ($bindings, $roleNames) {
            $roleId = $bindings->get($u->id)?->role_id;
            return [
                'id' => $u->id,
                'name' => $u->name,
                'phone' => $u->phone,
                'tenant_role_name' => $roleId ? ($roleNames->get($roleId) ?? null) : null,
                'created_at' => $u->created_at?->toIso8601String(),
            ];
        })->all();

        return Inertia::render('tenant/Users/Index', [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'q' => $q,
        ]);
    }

    public function create(Request $request): Response
    {
        $tenantId = $this->requireCurrentTenant($request);

        return Inertia::render('tenant/Users/Create', [
            'tenantRoles' => $this->tenantRoleOptions($tenantId),
        ]);
    }

    public function storeFromForm(Request $request): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8'],
            'tenant_role_id' => ['nullable', 'string', 'size:26'],
        ]);

        if (! empty($data['tenant_role_id'])) {
            $this->assertTenantRole($data['tenant_role_id'], $tenantId);
        }

        DB::transaction(function () use ($data, $tenantId) {
            $user = User::query()->where('phone', $data['phone'])->first();
            if (! $user) {
                $user = User::create([
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'password' => $data['password'],
                    'status' => 'active',
                    'is_platform_admin' => false,
                ]);
            }

            // 已有租户级 membership 视为重复邀请
            $existing = Membership::query()->withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('tenant_id', $tenantId)
                ->whereNull('store_id')
                ->where('status', 'active')
                ->exists();
            if ($existing) {
                throw ValidationException::withMessages([
                    'phone' => '该手机号已是当前租户成员',
                ]);
            }

            Membership::query()->withoutGlobalScopes()->create([
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'store_id' => null,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            if (! empty($data['tenant_role_id'])) {
                UserRoleBinding::query()->withoutGlobalScopes()->create([
                    'user_id' => $user->id,
                    'role_id' => $data['tenant_role_id'],
                    'tenant_id' => $tenantId,
                    'store_id' => null,
                    'status' => 'active',
                    'granted_at' => now(),
                ]);
            }
        });

        return redirect('/tenant/users')->with('success', '用户已添加');
    }

    public function edit(Request $request, string $userId): Response
    {
        $tenantId = $this->requireCurrentTenant($request);
        $user = $this->resolveTenantUser($userId, $tenantId);

        $binding = UserRoleBinding::query()->withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->whereNull('store_id')
            ->where('status', 'active')
            ->first(['role_id']);

        $storeBindingCount = Membership::query()->withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->whereNotNull('store_id')
            ->where('status', 'active')
            ->count();

        return Inertia::render('tenant/Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'tenant_role_id' => $binding?->role_id,
                'created_at' => $user->created_at?->toIso8601String(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'store_binding_count' => $storeBindingCount,
            ],
            'tenantRoles' => $this->tenantRoleOptions($tenantId),
        ]);
    }

    public function update(Request $request, string $userId): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        $user = $this->resolveTenantUser($userId, $tenantId);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'tenant_role_id' => ['nullable', 'string', 'size:26'],
        ]);

        if (! empty($data['tenant_role_id'])) {
            $this->assertTenantRole($data['tenant_role_id'], $tenantId);
        }

        DB::transaction(function () use ($user, $tenantId, $data) {
            $user->update(['name' => $data['name']]);

            // 关闭旧的 tenant-level 绑定（若存在）
            UserRoleBinding::query()->withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('tenant_id', $tenantId)
                ->whereNull('store_id')
                ->where('status', 'active')
                ->update(['status' => 'revoked']);

            if (! empty($data['tenant_role_id'])) {
                UserRoleBinding::query()->withoutGlobalScopes()->create([
                    'user_id' => $user->id,
                    'role_id' => $data['tenant_role_id'],
                    'tenant_id' => $tenantId,
                    'store_id' => null,
                    'status' => 'active',
                    'granted_at' => now(),
                ]);
            }
        });

        return back()->with('success', '已更新');
    }

    public function destroy(Request $request, string $userId): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        $user = $this->resolveTenantUser($userId, $tenantId);

        DB::transaction(function () use ($user, $tenantId) {
            // 撤销该用户在本租户的全部 active membership 与 role binding（含门店级）
            Membership::query()->withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->update(['status' => 'left']);

            UserRoleBinding::query()->withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->update(['status' => 'revoked']);
        });

        return back()->with('success', '已移除该用户');
    }

    public function resetPassword(Request $request, string $userId): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        $user = $this->resolveTenantUser($userId, $tenantId);

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user->update(['password' => $data['password']]);

        return back()->with('success', '密码已重置');
    }

    /**
     * 解析当前租户上下文。未登录或未选定时抛 422。
     */
    private function requireCurrentTenant(Request $request): string
    {
        $id = $request->session()->get('current_tenant_id');
        if (! $id) {
            throw ValidationException::withMessages([
                'tenant' => '尚未选定租户',
            ]);
        }
        return (string) $id;
    }

    /**
     * 确认 user 当前租户内有 active membership，否则 404。
     */
    private function resolveTenantUser(string $userId, string $tenantId): User
    {
        $member = Membership::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();
        if (! $member) {
            abort(404);
        }
        return User::query()->whereKey($userId)->firstOrFail();
    }

    /**
     * 当前租户可用的租户级角色：scope=tenant 且 (tenant_id=null OR tenant_id=current)。
     */
    private function assertTenantRole(string $roleId, string $tenantId): void
    {
        $ok = Role::query()->whereKey($roleId)
            ->where('scope', 'tenant')
            ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))
            ->exists();
        if (! $ok) {
            throw ValidationException::withMessages([
                'tenant_role_id' => '所选租户级角色不存在或不属于当前租户',
            ]);
        }
    }

    /**
     * @return array<int, array{id: string, name: string, is_system: bool}>
     */
    private function tenantRoleOptions(string $tenantId): array
    {
        return Role::query()
            ->where('scope', 'tenant')
            ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get(['id', 'name', 'is_system'])
            ->map(fn (Role $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'is_system' => (bool) $r->is_system,
            ])->all();
    }
}
