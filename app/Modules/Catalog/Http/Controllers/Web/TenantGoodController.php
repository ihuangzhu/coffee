<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Web;

use App\Modules\Catalog\Enums\GoodStatus;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Good;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 租户后台 - 商品（路径 /tenant/goods）。
 *
 * 单 SKU 模型：sku.{base_price_cents, code} 在前端嵌套提交，后端展平到 goods 表的
 * sku_base_price_cents / sku_code 列。attrs_json 当前未持久化（schema 预留）。
 */
class TenantGoodController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $this->requireCurrentTenant($request);

        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');
        $page = max(1, (int) $request->query('page', 1));
        $pageSize = min(100, max(5, (int) $request->query('per_page', 20)));

        $query = Good::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at');
        if ($q !== '') {
            $query->where('name', 'like', "%{$q}%");
        }
        if (in_array($status, ['active', 'off_shelf'], true)) {
            $query->where('status', $status);
        }

        $total = (clone $query)->count();
        $goods = $query->skip(($page - 1) * $pageSize)->take($pageSize)
            ->get(['id', 'name', 'category_id', 'sku_code', 'sku_base_price_cents', 'status', 'created_at']);

        $categoryNames = Category::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $goods->pluck('category_id'))
            ->pluck('name', 'id');

        $rows = $goods->map(fn (Good $g) => [
            'id' => $g->id,
            'name' => $g->name,
            'category_id' => $g->category_id,
            'category_name' => $categoryNames[$g->category_id] ?? null,
            'sku_base_price_cents' => (int) $g->sku_base_price_cents,
            'sku_code' => $g->sku_code,
            'status' => $g->status->value,
            'created_at' => $g->created_at?->toIso8601String(),
        ])->all();

        $hasCategories = Category::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->exists();

        return Inertia::render('tenant/Goods/Index', [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'q' => $q,
            'status' => $status,
            'has_categories' => $hasCategories,
        ]);
    }

    public function create(Request $request): Response
    {
        $tenantId = $this->requireCurrentTenant($request);

        return Inertia::render('tenant/Goods/Create', [
            'categories' => $this->categoryOptions($tenantId),
        ]);
    }

    public function storeFromForm(Request $request): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);

        $data = $this->validatePayload($request, $tenantId, withStatus: false);

        Good::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'category_id' => $data['category_id'],
            'sku_code' => $data['sku']['code'] ?: null,
            'sku_base_price_cents' => (int) $data['sku']['base_price_cents'],
            'status' => 'active',
        ]);

        return redirect('/tenant/goods')->with('success', '商品已创建');
    }

    public function edit(Request $request, string $id): Response
    {
        $tenantId = $this->requireCurrentTenant($request);
        $g = $this->resolve($tenantId, $id);

        return Inertia::render('tenant/Goods/Edit', [
            'goods' => [
                'id' => $g->id,
                'name' => $g->name,
                'category_id' => $g->category_id,
                'status' => $g->status->value,
            ],
            'sku' => [
                'id' => null,
                'base_price_cents' => (int) $g->sku_base_price_cents,
                'code' => $g->sku_code,
                'attrs_json' => null,
            ],
            'categories' => $this->categoryOptions($tenantId),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        $g = $this->resolve($tenantId, $id);

        $data = $this->validatePayload($request, $tenantId, withStatus: true, ignoreSkuId: $g->id);

        $g->update([
            'name' => $data['name'],
            'category_id' => $data['category_id'],
            'sku_code' => $data['sku']['code'] ?: null,
            'sku_base_price_cents' => (int) $data['sku']['base_price_cents'],
            'status' => GoodStatus::from($data['status']),
        ]);

        return back()->with('success', '已更新');
    }

    private function requireCurrentTenant(Request $request): string
    {
        $id = $request->session()->get('current_tenant_id');
        if (! $id) {
            throw ValidationException::withMessages(['tenant' => '尚未选定租户']);
        }
        return (string) $id;
    }

    private function resolve(string $tenantId, string $id): Good
    {
        $g = Good::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->whereKey($id)->first();
        if (! $g) {
            abort(404);
        }
        return $g;
    }

    /**
     * @return array<int, array{id:string,name:string}>
     */
    private function categoryOptions(string $tenantId): array
    {
        return Category::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderBy('sort')->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name])
            ->all();
    }

    /**
     * 校验提交的嵌套 sku 表单。store 不含 status；update 含。
     */
    private function validatePayload(Request $request, string $tenantId, bool $withStatus, ?string $ignoreSkuId = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'category_id' => ['required', 'string', 'size:26',
                Rule::exists('categories', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'sku' => ['required', 'array'],
            'sku.base_price_cents' => ['required', 'integer', 'min:0', 'max:99999999'],
            'sku.code' => ['nullable', 'string', 'max:50',
                Rule::unique('goods', 'sku_code')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNotNull('sku_code'))
                    ->ignore($ignoreSkuId, 'id'),
            ],
            'sku.attrs_json' => ['nullable'],
        ];
        if ($withStatus) {
            $rules['status'] = ['required', 'in:active,off_shelf'];
        }
        return $request->validate($rules);
    }
}
