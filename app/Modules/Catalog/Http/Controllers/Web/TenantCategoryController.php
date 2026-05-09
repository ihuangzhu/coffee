<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Web;

use App\Modules\Catalog\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 租户后台 - 分类管理（路径 /tenant/categories）。
 * 支持：树形展示、owner_type 双层（TENANT/STORE）、category_type 三态、item_type_scope 限制、
 * parent_id 路径计算、有子分类或被 items 引用时禁删。
 */
class TenantCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $this->requireCurrentTenant($request);

        $rows = Category::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderBy('owner_type')
            ->orderBy('path')
            ->orderBy('sort_no')
            ->orderBy('name')
            ->get([
                'id', 'parent_id', 'owner_type', 'owner_store_id',
                'category_type', 'item_type_scope', 'name', 'code',
                'level', 'path', 'sort_no', 'status',
            ])
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'parent_id' => $c->parent_id,
                'owner_type' => $c->owner_type->value,
                'owner_store_id' => $c->owner_store_id,
                'category_type' => $c->category_type->value,
                'item_type_scope' => $c->item_type_scope->value,
                'name' => $c->name,
                'code' => $c->code,
                'level' => (int) $c->level,
                'path' => $c->path,
                'sort_no' => (int) $c->sort_no,
                'status' => $c->status->value,
            ])->all();

        return Inertia::render('tenant/Categories/Index', [
            'rows' => $rows,
        ]);
    }

    public function create(Request $request): Response
    {
        $tenantId = $this->requireCurrentTenant($request);

        return Inertia::render('tenant/Categories/Create', [
            'parents' => $this->parentOptions($tenantId),
        ]);
    }

    public function storeFromForm(Request $request): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        $data = $this->validatePayload($request, $tenantId, ignoreId: null);

        $parent = null;
        if (! empty($data['parent_id'])) {
            $parent = $this->resolve($tenantId, $data['parent_id']);
            $this->assertSameOwner($parent, $data['owner_type'], $data['owner_store_id'] ?? null);
        }

        $pathAndLevel = (new Category())->computePathAndLevel($parent);

        Category::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'owner_type' => $data['owner_type'],
            'owner_store_id' => $data['owner_store_id'] ?? null,
            'category_type' => $data['category_type'],
            'item_type_scope' => $data['item_type_scope'],
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'level' => $pathAndLevel['level'],
            'path' => $pathAndLevel['path'],
            'sort_no' => $data['sort_no'] ?? 0,
            'status' => $data['status'] ?? 'active',
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
                'parent_id' => $cat->parent_id,
                'owner_type' => $cat->owner_type->value,
                'owner_store_id' => $cat->owner_store_id,
                'category_type' => $cat->category_type->value,
                'item_type_scope' => $cat->item_type_scope->value,
                'name' => $cat->name,
                'code' => $cat->code,
                'sort_no' => (int) $cat->sort_no,
                'status' => $cat->status->value,
            ],
            'parents' => $this->parentOptions($tenantId, excludeSubtreeOf: $cat),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        $cat = $this->resolve($tenantId, $id);
        $data = $this->validatePayload($request, $tenantId, ignoreId: $cat->id);

        $newParent = null;
        if (! empty($data['parent_id'])) {
            if ($data['parent_id'] === $cat->id) {
                throw ValidationException::withMessages(['parent_id' => '不能将分类挂到自身']);
            }
            $newParent = $this->resolve($tenantId, $data['parent_id']);
            if (str_contains($newParent->path, '/'.$cat->id.'/')) {
                throw ValidationException::withMessages(['parent_id' => '不能挂到自身的子分类下']);
            }
            $this->assertSameOwner($newParent, $data['owner_type'], $data['owner_store_id'] ?? null);
        }

        $pathAndLevel = $cat->computePathAndLevel($newParent);

        $cat->update([
            'owner_type' => $data['owner_type'],
            'owner_store_id' => $data['owner_store_id'] ?? null,
            'category_type' => $data['category_type'],
            'item_type_scope' => $data['item_type_scope'],
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'sort_no' => $data['sort_no'] ?? 0,
            'status' => $data['status'] ?? 'active',
            'level' => $pathAndLevel['level'],
            'path' => $pathAndLevel['path'],
        ]);

        return back()->with('success', '已更新');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        $cat = $this->resolve($tenantId, $id);

        $hasChildren = Category::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('parent_id', $cat->id)->exists();
        if ($hasChildren) {
            throw ValidationException::withMessages(['category' => '该分类下仍有子分类，无法删除']);
        }

        // 被 items 引用时禁删（business_category_id / inventory_category_id 任一）
        // 注：items 表在 Task 2 才创建；此查询在 Task 2 之前不会命中（表不存在则跳过）。
        if (Schema::hasTable('items')) {
            $referenced = DB::table('items')
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($cat) {
                    $q->where('business_category_id', $cat->id)
                        ->orWhere('inventory_category_id', $cat->id);
                })->exists();
            if ($referenced) {
                throw ValidationException::withMessages(['category' => '该分类被商品引用，无法删除']);
            }
        }

        $cat->delete();
        return back()->with('success', '已删除');
    }

    private function validatePayload(Request $request, string $tenantId, ?string $ignoreId): array
    {
        return $request->validate([
            'owner_type' => ['required', Rule::in(['TENANT', 'STORE'])],
            'owner_store_id' => ['nullable', 'string', 'size:26', 'required_if:owner_type,STORE'],
            'category_type' => ['required', Rule::in(['BUSINESS', 'INVENTORY', 'BOTH'])],
            'item_type_scope' => ['required', Rule::in([
                'SALE_PRODUCT', 'RAW_MATERIAL', 'SEMI_FINISHED',
                'FINISHED_GOOD', 'SERVICE', 'PACKAGE', 'ALL',
            ])],
            'parent_id' => ['nullable', 'string', 'size:26'],
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:64',
                Rule::unique('categories', 'code')
                    ->ignore($ignoreId)
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'sort_no' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'status' => ['nullable', Rule::in(['active', 'disabled'])],
        ]);
    }

    private function assertSameOwner(Category $parent, string $childOwnerType, ?string $childOwnerStoreId): void
    {
        if ($parent->owner_type->value !== $childOwnerType
            || $parent->owner_store_id !== $childOwnerStoreId) {
            throw ValidationException::withMessages([
                'parent_id' => '父分类与子分类必须是同一所有者',
            ]);
        }
    }

    private function parentOptions(string $tenantId, ?Category $excludeSubtreeOf = null): array
    {
        $query = Category::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('owner_type')->orderBy('path')->orderBy('sort_no');

        if ($excludeSubtreeOf) {
            $excludePrefix = $excludeSubtreeOf->path.$excludeSubtreeOf->id.'/';
            $query->where('id', '!=', $excludeSubtreeOf->id)
                ->where('path', 'NOT LIKE', $excludePrefix.'%');
        }

        return $query->get(['id', 'parent_id', 'owner_type', 'owner_store_id', 'name', 'level', 'path'])
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'parent_id' => $c->parent_id,
                'owner_type' => $c->owner_type->value,
                'owner_store_id' => $c->owner_store_id,
                'name' => $c->name,
                'level' => (int) $c->level,
                'path' => $c->path,
            ])->all();
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
