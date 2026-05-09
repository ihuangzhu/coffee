<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Web;

use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\Models\UserRoleBinding;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 租户后台 - 门店级成员（路径 /tenant/stores/{store}/users）。
 *
 * 数据模型：每行 = membership(scope=store, store_id=$store) + 同 user 同 store 的
 * active UserRoleBinding(scope=store)。
 *
 * 进入条件：当前 session 选定 tenant，且 store 属于该 tenant。
 */
class StoreUserController extends Controller
{
    public function index(Request $request, string $storeId): Response
    {
        [$tenantId, $store] = $this->resolveStore($request, $storeId);

        $q = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));
        $pageSize = min(100, max(5, (int) $request->query('per_page', 20)));

        // 该门店的活跃 store-level memberships，按 joined_at desc
        $memberships = Membership::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('store_id', $store->id)
            ->where('status', 'active')
            ->orderByDesc('joined_at');

        if ($q !== '') {
            $userIds = User::query()
                ->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%"))
                ->pluck('id');
            $memberships->whereIn('user_id', $userIds);
        }

        $total = (clone $memberships)->count();
        $rels = $memberships->skip(($page - 1) * $pageSize)->take($pageSize)
            ->get(['user_id', 'joined_at']);

        $users = User::query()->whereIn('id', $rels->pluck('user_id'))->get(['id', 'name', 'phone'])->keyBy('id');
        $bindings = UserRoleBinding::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('store_id', $store->id)
            ->where('status', 'active')
            ->whereIn('user_id', $rels->pluck('user_id'))
            ->get(['user_id', 'role_id'])
            ->keyBy('user_id');
        $roleNames = Role::query()->whereIn('id', $bindings->pluck('role_id')->unique()->all())
            ->get(['id', 'name', 'is_system'])->keyBy('id');

        $rows = $rels->map(function ($m) use ($users, $bindings, $roleNames) {
            $u = $users->get($m->user_id);
            $b = $bindings->get($m->user_id);
            $role = $b ? $roleNames->get($b->role_id) : null;
            return [
                'user_id' => $m->user_id,
                'name' => $u?->name ?? '',
                'phone' => $u?->phone ?? '',
                'role_id' => $b?->role_id ?? '',
                'role_name' => $role?->name ?? '',
                'role_is_system' => $role ? (bool) $role->is_system : false,
                'joined_at' => $m->joined_at?->toIso8601String() ?? '',
            ];
        })->values()->all();

        return Inertia::render('tenant/StoreUsers/Index', [
            'store' => ['id' => $store->id, 'name' => $store->name],
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'q' => $q,
        ]);
    }

    public function create(Request $request, string $storeId): Response
    {
        [$tenantId, $store] = $this->resolveStore($request, $storeId);

        // 候选：本租户活跃成员，但还不在本门店的 active membership 里
        $alreadyIn = Membership::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('store_id', $store->id)
            ->where('status', 'active')
            ->pluck('user_id')->all();

        $tenantUserIds = Membership::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->pluck('user_id')->unique()->all();

        $candidates = User::query()
            ->whereIn('id', array_values(array_diff($tenantUserIds, $alreadyIn)))
            ->orderBy('name')
            ->get(['id', 'name', 'phone'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'phone' => $u->phone])
            ->all();

        return Inertia::render('tenant/StoreUsers/Create', [
            'store' => ['id' => $store->id, 'name' => $store->name],
            'candidates' => $candidates,
            'storeRoles' => $this->storeRoleOptions($tenantId),
        ]);
    }

    public function storeFromForm(Request $request, string $storeId): RedirectResponse
    {
        [$tenantId, $store] = $this->resolveStore($request, $storeId);

        $data = $request->validate([
            'user_id' => ['required', 'string', 'size:26'],
            'role_id' => ['required', 'string', 'size:26'],
        ]);

        // user 必须是本租户活跃成员
        $isTenantMember = Membership::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $data['user_id'])
            ->where('status', 'active')->exists();
        if (! $isTenantMember) {
            throw ValidationException::withMessages(['user_id' => '该用户不是本租户成员']);
        }

        // role 必须是 scope=store 且属于本租户或全局模板
        $this->assertStoreRole($data['role_id'], $tenantId);

        // 重复加入校验
        $duplicate = Membership::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('store_id', $store->id)
            ->where('user_id', $data['user_id'])
            ->where('status', 'active')->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['user_id' => '该用户已在本门店']);
        }

        DB::transaction(function () use ($data, $tenantId, $store) {
            Membership::query()->withoutGlobalScopes()->create([
                'user_id' => $data['user_id'],
                'tenant_id' => $tenantId,
                'store_id' => $store->id,
                'status' => 'active',
                'joined_at' => now(),
            ]);
            UserRoleBinding::query()->withoutGlobalScopes()->create([
                'user_id' => $data['user_id'],
                'role_id' => $data['role_id'],
                'tenant_id' => $tenantId,
                'store_id' => $store->id,
                'status' => 'active',
                'granted_at' => now(),
            ]);
        });

        return redirect("/tenant/stores/{$store->id}/users")->with('success', '已加入门店');
    }

    public function edit(Request $request, string $storeId, string $userId): Response
    {
        [$tenantId, $store] = $this->resolveStore($request, $storeId);
        $u = $this->resolveStoreMember($tenantId, $store->id, $userId);

        $binding = UserRoleBinding::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('store_id', $store->id)
            ->where('user_id', $u->id)
            ->where('status', 'active')
            ->first(['role_id']);

        return Inertia::render('tenant/StoreUsers/Edit', [
            'store' => ['id' => $store->id, 'name' => $store->name],
            'binding' => [
                'user_id' => $u->id,
                'name' => $u->name,
                'phone' => $u->phone,
                'role_id' => $binding?->role_id ?? '',
            ],
            'storeRoles' => $this->storeRoleOptions($tenantId),
        ]);
    }

    public function update(Request $request, string $storeId, string $userId): RedirectResponse
    {
        [$tenantId, $store] = $this->resolveStore($request, $storeId);
        $u = $this->resolveStoreMember($tenantId, $store->id, $userId);

        $data = $request->validate([
            'role_id' => ['required', 'string', 'size:26'],
        ]);
        $this->assertStoreRole($data['role_id'], $tenantId);

        DB::transaction(function () use ($u, $tenantId, $store, $data) {
            UserRoleBinding::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('store_id', $store->id)
                ->where('user_id', $u->id)
                ->where('status', 'active')
                ->update(['status' => 'revoked']);
            UserRoleBinding::query()->withoutGlobalScopes()->create([
                'user_id' => $u->id,
                'role_id' => $data['role_id'],
                'tenant_id' => $tenantId,
                'store_id' => $store->id,
                'status' => 'active',
                'granted_at' => now(),
            ]);
        });

        return back()->with('success', '已更新');
    }

    public function destroy(Request $request, string $storeId, string $userId): RedirectResponse
    {
        [$tenantId, $store] = $this->resolveStore($request, $storeId);
        $u = $this->resolveStoreMember($tenantId, $store->id, $userId);

        DB::transaction(function () use ($u, $tenantId, $store) {
            Membership::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('store_id', $store->id)
                ->where('user_id', $u->id)
                ->where('status', 'active')
                ->update(['status' => 'left']);
            UserRoleBinding::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('store_id', $store->id)
                ->where('user_id', $u->id)
                ->where('status', 'active')
                ->update(['status' => 'revoked']);
        });

        return back()->with('success', '已移出门店');
    }

    /**
     * @return array{0: string, 1: \App\Modules\Tenancy\Models\Store}
     */
    private function resolveStore(Request $request, string $storeId): array
    {
        $tenantId = $request->session()->get('current_tenant_id');
        if (! $tenantId) {
            throw ValidationException::withMessages(['tenant' => '尚未选定租户']);
        }
        $store = Store::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereKey($storeId)
            ->first();
        if (! $store) {
            abort(404);
        }
        return [(string) $tenantId, $store];
    }

    private function resolveStoreMember(string $tenantId, string $storeId, string $userId): User
    {
        $exists = Membership::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('store_id', $storeId)
            ->where('user_id', $userId)
            ->where('status', 'active')->exists();
        if (! $exists) {
            abort(404);
        }
        return User::query()->whereKey($userId)->firstOrFail();
    }

    private function assertStoreRole(string $roleId, string $tenantId): void
    {
        $ok = Role::query()->whereKey($roleId)
            ->where('scope', 'store')
            ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))
            ->exists();
        if (! $ok) {
            throw ValidationException::withMessages(['role_id' => '所选门店级角色不存在']);
        }
    }

    /**
     * @return array<int, array{id:string,name:string,is_system:bool}>
     */
    private function storeRoleOptions(string $tenantId): array
    {
        return Role::query()
            ->where('scope', 'store')
            ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get(['id', 'name', 'is_system'])
            ->map(fn (Role $r) => [
                'id' => $r->id, 'name' => $r->name, 'is_system' => (bool) $r->is_system,
            ])->all();
    }
}
