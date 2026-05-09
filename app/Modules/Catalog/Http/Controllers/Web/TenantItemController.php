<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Web;

use App\Modules\Catalog\Enums\ItemStatus;
use App\Modules\Catalog\Enums\ItemType;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Catalog\Models\ProductInventoryPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 租户后台 - 物料（Item）。前端嵌套提交 sku（单 SKU 时一行）+ policy（一对一）。
 * 后端使用事务确保 item / sku / policy 三件同步建立。
 */
class TenantItemController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $this->requireCurrentTenant($request);

        $q = trim((string) $request->query('q', ''));
        $itemType = (string) $request->query('item_type', 'all');
        $status = (string) $request->query('status', 'all');
        $page = max(1, (int) $request->query('page', 1));
        $pageSize = min(100, max(5, (int) $request->query('per_page', 20)));

        $query = Item::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at');
        if ($q !== '') {
            $query->where('item_name', 'like', "%{$q}%");
        }
        if (in_array($itemType, array_column(ItemType::cases(), 'value'), true)) {
            $query->where('item_type', $itemType);
        }
        if (in_array($status, ['active', 'off_shelf'], true)) {
            $query->where('status', $status);
        }

        $total = (clone $query)->count();
        $items = $query->skip(($page - 1) * $pageSize)->take($pageSize)->get();

        $catIds = $items->pluck('business_category_id')
            ->merge($items->pluck('inventory_category_id'))
            ->filter()->unique()->values();
        $categoryNames = Category::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $catIds)
            ->pluck('name', 'id');

        $skuMap = ItemSku::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('item_id', $items->pluck('id'))
            ->get()->groupBy('item_id');

        $rows = $items->map(fn (Item $i) => [
            'id' => $i->id,
            'item_name' => $i->item_name,
            'item_type' => $i->item_type->value,
            'business_category_id' => $i->business_category_id,
            'business_category_name' => $i->business_category_id ? ($categoryNames[$i->business_category_id] ?? null) : null,
            'inventory_category_id' => $i->inventory_category_id,
            'inventory_category_name' => $i->inventory_category_id ? ($categoryNames[$i->inventory_category_id] ?? null) : null,
            'unit' => $i->unit,
            'sku_count' => $skuMap->get($i->id)?->count() ?? 0,
            'first_sku_price_cents' => (int) ($skuMap->get($i->id)?->first()?->sale_price_cents ?? 0),
            'inventory_enabled' => (bool) $i->inventory_enabled,
            'status' => $i->status->value,
            'created_at' => $i->created_at?->toIso8601String(),
        ])->all();

        $hasCategories = Category::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->exists();

        return Inertia::render('tenant/Items/Index', [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'q' => $q,
            'item_type' => $itemType,
            'status' => $status,
            'has_categories' => $hasCategories,
            'item_types' => array_column(ItemType::cases(), 'value'),
        ]);
    }

    public function create(Request $request): Response
    {
        $tenantId = $this->requireCurrentTenant($request);

        return Inertia::render('tenant/Items/Create', [
            'categories' => $this->categoryOptions($tenantId),
            'item_types' => array_column(ItemType::cases(), 'value'),
        ]);
    }

    public function storeFromForm(Request $request): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        $data = $this->validatePayload($request, $tenantId, withStatus: false);

        DB::transaction(function () use ($tenantId, $data) {
            $item = Item::query()->withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'item_name' => $data['item_name'],
                'item_type' => $data['item_type'],
                'business_category_id' => $data['business_category_id'] ?: null,
                'inventory_category_id' => $data['inventory_category_id'] ?: null,
                'unit' => $data['unit'],
                'sku_enabled' => true,
                'inventory_enabled' => $data['inventory_enabled'],
                'status' => 'active',
            ]);

            // 创建首个 SKU；observer 自动建 policy
            $sku = ItemSku::query()->withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'item_id' => $item->id,
                'spec_json' => $data['sku']['spec_json'] ?? [],
                'barcode' => $data['sku']['barcode'] ?: null,
                'sale_price_cents' => (int) $data['sku']['sale_price_cents'],
                'cost_price_cents' => (int) ($data['sku']['cost_price_cents'] ?? 0),
                'inventory_enabled' => true,
            ]);

            // 写入 policy 用户传值（覆盖 observer 默认）
            if (! empty($data['policy'])) {
                ProductInventoryPolicy::query()->withoutGlobalScopes()
                    ->where('sku_id', $sku->id)
                    ->update([
                        'inventory_track_type' => $data['policy']['inventory_track_type'],
                        'stock_deduct_mode' => $data['policy']['stock_deduct_mode'],
                        'allow_negative_stock' => (bool) $data['policy']['allow_negative_stock'],
                        'batch_required' => (bool) $data['policy']['batch_required'],
                        'expiry_required' => (bool) $data['policy']['expiry_required'],
                    ]);
            }
        });

        return redirect('/tenant/items')->with('success', '物料已创建');
    }

    public function edit(Request $request, string $id): Response
    {
        $tenantId = $this->requireCurrentTenant($request);
        $item = $this->resolve($tenantId, $id);
        $sku = $item->skus()->withoutGlobalScopes()->first();
        $policy = $sku ? ProductInventoryPolicy::query()->withoutGlobalScopes()
            ->where('sku_id', $sku->id)->first() : null;

        return Inertia::render('tenant/Items/Edit', [
            'item' => [
                'id' => $item->id,
                'item_name' => $item->item_name,
                'item_type' => $item->item_type->value,
                'business_category_id' => $item->business_category_id,
                'inventory_category_id' => $item->inventory_category_id,
                'unit' => $item->unit,
                'inventory_enabled' => (bool) $item->inventory_enabled,
                'status' => $item->status->value,
            ],
            'sku' => $sku ? [
                'id' => $sku->id,
                'spec_json' => $sku->spec_json,
                'barcode' => $sku->barcode,
                'sale_price_cents' => (int) $sku->sale_price_cents,
                'cost_price_cents' => (int) $sku->cost_price_cents,
            ] : null,
            'policy' => $policy ? [
                'inventory_track_type' => $policy->inventory_track_type->value,
                'stock_deduct_mode' => $policy->stock_deduct_mode->value,
                'allow_negative_stock' => (bool) $policy->allow_negative_stock,
                'batch_required' => (bool) $policy->batch_required,
                'expiry_required' => (bool) $policy->expiry_required,
            ] : null,
            'categories' => $this->categoryOptions($tenantId),
            'item_types' => array_column(ItemType::cases(), 'value'),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        $item = $this->resolve($tenantId, $id);
        $sku = $item->skus()->withoutGlobalScopes()->first();

        $data = $this->validatePayload($request, $tenantId, withStatus: true, ignoreSkuId: $sku?->id);

        DB::transaction(function () use ($item, $sku, $data) {
            $item->update([
                'item_name' => $data['item_name'],
                'item_type' => $data['item_type'],
                'business_category_id' => $data['business_category_id'] ?: null,
                'inventory_category_id' => $data['inventory_category_id'] ?: null,
                'unit' => $data['unit'],
                'inventory_enabled' => (bool) $data['inventory_enabled'],
                'status' => ItemStatus::from($data['status']),
            ]);

            if ($sku) {
                $sku->update([
                    'spec_json' => $data['sku']['spec_json'] ?? [],
                    'barcode' => $data['sku']['barcode'] ?: null,
                    'sale_price_cents' => (int) $data['sku']['sale_price_cents'],
                    'cost_price_cents' => (int) ($data['sku']['cost_price_cents'] ?? 0),
                ]);

                if (! empty($data['policy'])) {
                    ProductInventoryPolicy::query()->withoutGlobalScopes()
                        ->where('sku_id', $sku->id)
                        ->update([
                            'inventory_track_type' => $data['policy']['inventory_track_type'],
                            'stock_deduct_mode' => $data['policy']['stock_deduct_mode'],
                            'allow_negative_stock' => (bool) $data['policy']['allow_negative_stock'],
                            'batch_required' => (bool) $data['policy']['batch_required'],
                            'expiry_required' => (bool) $data['policy']['expiry_required'],
                        ]);
                }
            }
        });

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

    private function resolve(string $tenantId, string $id): Item
    {
        $i = Item::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->whereKey($id)->first();
        if (! $i) {
            abort(404);
        }
        return $i;
    }

    /**
     * 返回所有租户级激活分类（含 owner_type / category_type / item_type_scope / level / path），
     * 前端按 category_type 与 item_type_scope 自行筛选 business / inventory 下拉。
     *
     * @return array<int, array{id:string,name:string,owner_type:string,category_type:string,item_type_scope:string,level:int,path:string}>
     */
    private function categoryOptions(string $tenantId): array
    {
        return Category::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('owner_type')->orderBy('path')->orderBy('sort_no')->orderBy('name')
            ->get(['id', 'name', 'owner_type', 'category_type', 'item_type_scope', 'level', 'path'])
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'owner_type' => $c->owner_type->value,
                'category_type' => $c->category_type->value,
                'item_type_scope' => $c->item_type_scope->value,
                'level' => (int) $c->level,
                'path' => $c->path,
            ])->all();
    }

    private function validatePayload(Request $request, string $tenantId, bool $withStatus, ?string $ignoreSkuId = null): array
    {
        $itemTypes = array_column(ItemType::cases(), 'value');
        $itemTypeForScope = (string) $request->input('item_type', '');
        $rules = [
            'item_name' => ['required', 'string', 'max:120'],
            'item_type' => ['required', Rule::in($itemTypes)],
            'business_category_id' => ['nullable', 'string', 'size:26',
                Rule::exists('categories', 'id')->where(function ($q) use ($tenantId, $itemTypeForScope) {
                    $q->where('tenant_id', $tenantId)
                        ->whereIn('category_type', ['BUSINESS', 'BOTH'])
                        ->where(function ($q2) use ($itemTypeForScope) {
                            $q2->where('item_type_scope', 'ALL');
                            if ($itemTypeForScope !== '') {
                                $q2->orWhere('item_type_scope', $itemTypeForScope);
                            }
                        });
                }),
            ],
            'inventory_category_id' => ['nullable', 'string', 'size:26',
                Rule::exists('categories', 'id')->where(function ($q) use ($tenantId, $itemTypeForScope) {
                    $q->where('tenant_id', $tenantId)
                        ->whereIn('category_type', ['INVENTORY', 'BOTH'])
                        ->where(function ($q2) use ($itemTypeForScope) {
                            $q2->where('item_type_scope', 'ALL');
                            if ($itemTypeForScope !== '') {
                                $q2->orWhere('item_type_scope', $itemTypeForScope);
                            }
                        });
                }),
            ],
            'unit' => ['required', 'string', 'max:20'],
            'inventory_enabled' => ['required', 'boolean'],
            'sku' => ['required', 'array'],
            'sku.spec_json' => ['nullable', 'array'],
            'sku.barcode' => ['nullable', 'string', 'max:64',
                Rule::unique('item_skus', 'barcode')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNotNull('barcode'))
                    ->ignore($ignoreSkuId, 'id'),
            ],
            'sku.sale_price_cents' => ['required', 'integer', 'min:0', 'max:99999999'],
            'sku.cost_price_cents' => ['nullable', 'integer', 'min:0', 'max:99999999'],
            'policy' => ['nullable', 'array'],
            'policy.inventory_track_type' => ['nullable', Rule::in(['NONE', 'FINISHED_GOOD', 'RAW_MATERIAL', 'BOTH'])],
            'policy.stock_deduct_mode' => ['nullable', Rule::in(['SALE_DEDUCT', 'MANUAL_DEDUCT', 'PRODUCTION_DEDUCT'])],
            'policy.allow_negative_stock' => ['nullable', 'boolean'],
            'policy.batch_required' => ['nullable', 'boolean'],
            'policy.expiry_required' => ['nullable', 'boolean'],
        ];
        if ($withStatus) {
            $rules['status'] = ['required', 'in:active,off_shelf'];
        }
        return $request->validate($rules);
    }
}
