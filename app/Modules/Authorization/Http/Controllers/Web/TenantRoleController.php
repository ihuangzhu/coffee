<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Http\Controllers\Web;

use App\Modules\Authorization\Enums\Permission;
use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\Models\UserRoleBinding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 租户后台角色管理（Web/Inertia，路径 /tenant/roles）。
 * 当前租户从 session('current_tenant_id') 解析。
 */
class TenantRoleController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $this->requireCurrentTenant($request);

        $roles = Role::query()
            ->where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id'))
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get(['id', 'tenant_id', 'name', 'code', 'scope', 'permissions', 'is_system']);

        // 计算各角色在用人数（仅本租户活跃 binding 计数）
        $roleIds = $roles->pluck('id')->all();
        $usage = UserRoleBinding::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('role_id', $roleIds)
            ->where('status', 'active')
            ->select('role_id', DB::raw('COUNT(DISTINCT user_id) AS c'))
            ->groupBy('role_id')
            ->pluck('c', 'role_id');

        $rows = $roles->map(fn (Role $r) => [
            'id' => $r->id,
            'tenant_id' => $r->tenant_id,
            'name' => $r->name,
            'scope' => $r->scope,
            'permissions' => $r->permissions ?? [],
            'is_system' => (bool) $r->is_system,
            'in_use_count' => (int) ($usage[$r->id] ?? 0),
        ])->all();

        return Inertia::render('tenant/Roles/Index', [
            'roles' => $rows,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->requireCurrentTenant($request);

        return Inertia::render('tenant/Roles/Create', [
            'groupedPermissions' => $this->groupedPermissions(),
        ]);
    }

    public function storeFromForm(Request $request): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);

        $allowed = array_map(fn (Permission $p) => $p->value, Permission::cases());

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'scope' => ['required', 'in:tenant,store'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'in:'.implode(',', $allowed)],
        ]);

        Role::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'code' => $this->generateCode($data['name']),
            'scope' => $data['scope'],
            'permissions' => $data['permissions'] ?? [],
            'is_system' => false,
        ]);

        return redirect('/tenant/roles')->with('success', '角色已创建');
    }

    public function edit(Request $request, string $roleId): Response
    {
        $tenantId = $this->requireCurrentTenant($request);
        $role = $this->mustOwnedRole($roleId, $tenantId);

        return Inertia::render('tenant/Roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'scope' => $role->scope,
                'permissions' => $role->permissions ?? [],
                'is_system' => (bool) $role->is_system,
            ],
            'groupedPermissions' => $this->groupedPermissions(),
        ]);
    }

    public function update(Request $request, string $roleId): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        $role = $this->mustOwnedRole($roleId, $tenantId);

        $allowed = array_map(fn (Permission $p) => $p->value, Permission::cases());

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'scope' => ['required', 'in:tenant,store'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'in:'.implode(',', $allowed)],
        ]);

        $role->fill([
            'name' => $data['name'],
            'scope' => $data['scope'],
            'permissions' => $data['permissions'] ?? [],
        ])->save();

        return back()->with('success', '已更新');
    }

    public function destroy(Request $request, string $roleId): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        $role = $this->mustOwnedRole($roleId, $tenantId);

        $inUse = UserRoleBinding::query()->withoutGlobalScopes()
            ->where('role_id', $role->id)
            ->where('status', 'active')
            ->exists();
        if ($inUse) {
            throw ValidationException::withMessages([
                'role' => '该角色仍在使用中，无法删除',
            ]);
        }

        $role->delete();

        return back()->with('success', '已删除');
    }

    private function requireCurrentTenant(Request $request): string
    {
        $id = $request->session()->get('current_tenant_id');
        if (! $id) {
            throw ValidationException::withMessages(['tenant' => '尚未选定租户']);
        }
        return (string) $id;
    }

    /**
     * 必须是本租户自建角色（系统全局模板与他租户角色一律 404）。
     */
    private function mustOwnedRole(string $roleId, string $tenantId): Role
    {
        $role = Role::query()->whereKey($roleId)->first();
        if (! $role || $role->tenant_id !== $tenantId || $role->is_system) {
            abort(404);
        }
        return $role;
    }

    /**
     * 把 Permission 枚举按 'domain.action' 的 domain 分桶。
     *
     * @return array<string, array<int, string>>
     */
    private function groupedPermissions(): array
    {
        $grouped = [];
        foreach (Permission::cases() as $p) {
            $domain = explode('.', $p->value, 2)[0];
            $grouped[$domain] ??= [];
            $grouped[$domain][] = $p->value;
        }
        return $grouped;
    }

    private function generateCode(string $name): string
    {
        $slug = Str::slug($name) ?: 'role';
        return strtoupper(substr($slug, 0, 30)).'_'.strtoupper(Str::random(6));
    }
}
