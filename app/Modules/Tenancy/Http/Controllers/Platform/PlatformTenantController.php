<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers\Platform;

use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Enums\TenantStatus;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlatformTenantController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');
        $page = max(1, (int) $request->query('page', 1));
        $pageSize = min(100, max(5, (int) $request->query('per_page', 20)));

        $query = Tenant::query()->orderByDesc('created_at');
        if ($q !== '') {
            $query->where('name', 'like', "%{$q}%");
        }
        if (in_array($status, ['active', 'disabled'], true)) {
            $query->where('status', $status);
        }

        $total = (clone $query)->count();
        $rows = $query
            ->skip(($page - 1) * $pageSize)
            ->take($pageSize)
            ->get(['id', 'name', 'status', 'created_at'])
            ->map(fn (Tenant $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'status' => $t->status->value,
                'created_at' => $t->created_at?->toIso8601String(),
            ])->all();

        return Inertia::render('platform/Tenants/Index', [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'q' => $q,
            'status' => $status,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('platform/Tenants/Create');
    }

    public function edit(string $id): Response
    {
        $tenant = Tenant::query()->whereKey($id)->firstOrFail();

        return Inertia::render('platform/Tenants/Edit', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'status' => $tenant->status->value,
            ],
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $tenant = Tenant::query()->whereKey($id)->firstOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'status' => ['required', 'in:active,disabled'],
        ]);

        $tenant->update([
            'name' => $data['name'],
            'status' => TenantStatus::from($data['status']),
        ]);

        return back()->with('success', '已更新');
    }

    /**
     * Inertia 表单提交：新建租户 + 初始店主（事务）。
     * - tenant_name 唯一
     * - owner_phone 已存在的 user 复用；不存在则建新 User，密码长度 ≥8
     * - 写一行 status=active、store_id=null 的租户级 membership 作为店主
     */
    public function storeFromForm(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tenant_name' => ['required', 'string', 'max:100', 'unique:tenants,name'],
            'owner_name' => ['required', 'string', 'max:50'],
            'owner_phone' => ['required', 'string', 'max:20'],
            'owner_password' => ['required', 'string', 'min:8'],
        ]);

        DB::transaction(function () use ($data) {
            $tenant = Tenant::create([
                'name' => $data['tenant_name'],
                'status' => 'active',
            ]);

            $user = User::query()->where('phone', $data['owner_phone'])->first();
            if (! $user) {
                $user = User::create([
                    'name' => $data['owner_name'],
                    'phone' => $data['owner_phone'],
                    'password' => $data['owner_password'],
                    'status' => 'active',
                    'is_platform_admin' => false,
                ]);
            }

            Membership::query()->withoutGlobalScopes()->create([
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'store_id' => null,
                'status' => 'active',
                'joined_at' => now(),
            ]);
        });

        return redirect('/platform/tenants')->with('success', '租户已创建');
    }

    /**
     * 旧 JSON API（保留给 /api/platform/tenants 路径用，前端 Inertia 走 storeFromForm）。
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $tenant = Tenant::create([
            'name' => $data['name'],
            'status' => 'active',
        ]);

        return response()->json([
            'id' => $tenant->id,
            'name' => $tenant->name,
            'status' => $tenant->status->value,
        ], 201);
    }
}
