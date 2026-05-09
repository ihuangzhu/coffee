<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers\Web;

use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Inventory\Models\Bom;
use App\Modules\Inventory\Models\BomComponent;
use App\Modules\Tenancy\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TenantBomController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $this->requireCurrentTenant($request);
        $bomType = $request->query('bom_type');

        $boms = Bom::query()
            ->with(['outputSku.item', 'components'])
            ->when($bomType, fn ($q) => $q->where('bom_type', $bomType))
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('tenant/Bom/Index', [
            'boms' => $boms,
            'filterBomType' => $bomType,
        ]);
    }

    public function create(Request $request): Response
    {
        $tenantId = $this->requireCurrentTenant($request);
        return Inertia::render('tenant/Bom/Create', $this->formProps($tenantId));
    }

    public function storeFromForm(Request $request): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        $data = $this->validateForm($request, $tenantId, null);

        DB::transaction(function () use ($tenantId, $data) {
            $bom = Bom::query()->create([
                'tenant_id' => $tenantId,
                'output_sku_id' => $data['output_sku_id'],
                'output_qty' => (string) $data['output_qty'],
                'bom_type' => $data['bom_type'],
                'store_id' => $data['store_id'] ?? null,
                'status' => $data['status'],
            ]);

            foreach ($data['components'] as $row) {
                BomComponent::query()->create([
                    'bom_id' => $bom->id,
                    'component_sku_id' => $row['component_sku_id'],
                    'consume_qty' => (string) $row['consume_qty'],
                    'loss_rate' => (string) $row['loss_rate'],
                    'sequence_no' => (int) $row['sequence_no'],
                ]);
            }
        });

        return redirect('/tenant/boms')->with('success', '配方已创建');
    }

    public function edit(Request $request, string $id): Response
    {
        $tenantId = $this->requireCurrentTenant($request);
        $bom = Bom::query()->with('components')->findOrFail($id);

        return Inertia::render('tenant/Bom/Edit', array_merge(
            $this->formProps($tenantId),
            ['bom' => $bom]
        ));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $tenantId = $this->requireCurrentTenant($request);
        $bom = Bom::query()->findOrFail($id);
        $data = $this->validateForm($request, $tenantId, $bom->id);

        DB::transaction(function () use ($bom, $data) {
            $bom->update([
                'output_sku_id' => $data['output_sku_id'],
                'output_qty' => (string) $data['output_qty'],
                'bom_type' => $data['bom_type'],
                'store_id' => $data['store_id'] ?? null,
                'status' => $data['status'],
            ]);

            BomComponent::query()->where('bom_id', $bom->id)->delete();
            foreach ($data['components'] as $row) {
                BomComponent::query()->create([
                    'bom_id' => $bom->id,
                    'component_sku_id' => $row['component_sku_id'],
                    'consume_qty' => (string) $row['consume_qty'],
                    'loss_rate' => (string) $row['loss_rate'],
                    'sequence_no' => (int) $row['sequence_no'],
                ]);
            }
        });

        return redirect('/tenant/boms')->with('success', '配方已更新');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->requireCurrentTenant($request);
        $bom = Bom::query()->findOrFail($id);
        $bom->delete();

        return redirect('/tenant/boms')->with('success', '配方已删除');
    }

    /**
     * 提供给 create / edit 表单的下拉数据。
     *
     * @return array{outputSkus: \Illuminate\Support\Collection, componentSkus: \Illuminate\Support\Collection, stores: \Illuminate\Support\Collection}
     */
    private function formProps(string $tenantId): array
    {
        return [
            'outputSkus' => ItemSku::query()->with('item')
                ->whereHas('item', fn ($q) => $q->whereIn('item_type',
                    ['SALE_PRODUCT', 'FINISHED_GOOD', 'SEMI_FINISHED']))
                ->get(['id', 'item_id', 'sku_code', 'spec_name']),
            'componentSkus' => ItemSku::query()->with('item')
                ->whereHas('item', fn ($q) => $q->whereIn('item_type',
                    ['RAW_MATERIAL', 'SEMI_FINISHED', 'PACKAGE']))
                ->get(['id', 'item_id', 'sku_code', 'spec_name']),
            'stores' => Store::query()->where('tenant_id', $tenantId)
                ->get(['id', 'name']),
        ];
    }

    /**
     * @return array{output_sku_id:string,output_qty:numeric-string|float|int,bom_type:string,store_id:?string,status:string,components:array<int, array{component_sku_id:string,consume_qty:numeric-string|float|int,loss_rate:numeric-string|float|int,sequence_no:int}>}
     */
    private function validateForm(Request $request, string $tenantId, ?string $excludeBomId): array
    {
        $rules = [
            'output_sku_id' => ['required', 'string', 'size:26',
                Rule::exists('item_skus', 'id')->where('tenant_id', $tenantId)],
            'output_qty' => ['required', 'numeric', 'gt:0'],
            'bom_type' => ['required', Rule::in(['STANDARD', 'STORE_CUSTOM'])],
            'store_id' => [
                Rule::requiredIf(fn () => $request->input('bom_type') === 'STORE_CUSTOM'),
                Rule::prohibitedIf(fn () => $request->input('bom_type') === 'STANDARD'),
                'nullable', 'string', 'size:26',
                Rule::exists('stores', 'id')->where('tenant_id', $tenantId),
            ],
            'status' => ['required', Rule::in(['active', 'disabled'])],
            'components' => ['required', 'array', 'min:1'],
            'components.*.component_sku_id' => ['required', 'string', 'size:26',
                Rule::exists('item_skus', 'id')->where('tenant_id', $tenantId)],
            'components.*.consume_qty' => ['required', 'numeric', 'gt:0'],
            'components.*.loss_rate' => ['required', 'numeric', 'gte:0', 'lte:1'],
            'components.*.sequence_no' => ['required', 'integer', 'gte:0'],
        ];

        $data = $request->validate($rules);

        // 业务校验：item_type / 自引用 / 唯一性
        $errors = [];
        $outputSku = ItemSku::query()->with('item')->find($data['output_sku_id']);
        if ($outputSku && ! in_array($outputSku->item->item_type->value,
            ['SALE_PRODUCT', 'FINISHED_GOOD', 'SEMI_FINISHED'], true)) {
            $errors['output_sku_id'] = ['产出 SKU 必须是销售品 / 成品 / 半成品类型'];
        }

        foreach ($data['components'] as $i => $row) {
            if ($row['component_sku_id'] === $data['output_sku_id']) {
                $errors["components.{$i}.component_sku_id"] = ['组件 SKU 不能等于产出 SKU'];
                continue;
            }
            $compSku = ItemSku::query()->with('item')->find($row['component_sku_id']);
            if ($compSku && ! in_array($compSku->item->item_type->value,
                ['RAW_MATERIAL', 'SEMI_FINISHED', 'PACKAGE'], true)) {
                $errors["components.{$i}.component_sku_id"] = ['组件 SKU 必须是原料 / 半成品 / 包材类型'];
            }
        }

        // 唯一性：同 (output_sku, bom_type, store_id, status='active', deleted_at NULL) 至多 1 条
        if ($data['status'] === 'active') {
            $dupQuery = Bom::query()
                ->where('tenant_id', $tenantId)
                ->where('output_sku_id', $data['output_sku_id'])
                ->where('bom_type', $data['bom_type'])
                ->where('status', 'active');
            if ($data['bom_type'] === 'STORE_CUSTOM') {
                $dupQuery->where('store_id', $data['store_id']);
            } else {
                $dupQuery->whereNull('store_id');
            }
            if ($excludeBomId) {
                $dupQuery->whereKeyNot($excludeBomId);
            }
            if ($dupQuery->exists()) {
                $errors['output_sku_id'] = ['同产出 SKU 同类型已有 active 配方'];
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }

    private function requireCurrentTenant(Request $request): string
    {
        $id = $request->session()->get('current_tenant_id');
        if (! $id) {
            throw ValidationException::withMessages(['tenant' => '尚未选定租户']);
        }
        return (string) $id;
    }
}
