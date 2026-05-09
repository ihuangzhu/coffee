<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Web;

use App\Modules\Catalog\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 租户后台 - 商品分类管理（路径 /tenant/categories）。
 */
class TenantCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $this->requireCurrentTenant($request);

        $page = max(1, (int) $request->query('page', 1));
        $pageSize = min(100, max(5, (int) $request->query('per_page', 20)));

        $query = Category::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderBy('sort')
            ->orderBy('name');

        $total = (clone $query)->count();
        $categories = $query->skip(($page - 1) * $pageSize)->take($pageSize)
            ->get(['id', 'name', 'sort']);

        $rows = $categories->map(fn (Category $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'sort' => (int) $c->sort,
        ])->all();

        return Inertia::render('tenant/Categories/Index', [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->requireCurrentTenant($request);
        return Inertia::render('tenant/Categories/Create');
    }

    public function storeFromForm(Request $request): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100',
                Rule::unique('categories', 'name')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        Category::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'sort' => $data['sort'] ?? 0,
        ]);

        return redirect('/tenant/categories')->with('success', '分类已创建');
    }

    public function edit(Request $request, string $id): Response
    {
        $tenantId = $this->requireCurrentTenant($request);
        $cat = $this->resolve($tenantId, $id);

        return Inertia::render('tenant/Categories/Edit', [
            'category' => [
                'id' => $cat->id,
                'name' => $cat->name,
                'sort' => (int) $cat->sort,
            ],
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        $cat = $this->resolve($tenantId, $id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100',
                Rule::unique('categories', 'name')
                    ->ignore($cat->id)
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $cat->update([
            'name' => $data['name'],
            'sort' => $data['sort'] ?? 0,
        ]);

        return back()->with('success', '已更新');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        $cat = $this->resolve($tenantId, $id);

        $cat->delete();
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

    private function resolve(string $tenantId, string $id): Category
    {
        $cat = Category::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereKey($id)->first();
        if (! $cat) {
            abort(404);
        }
        return $cat;
    }
}
