<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import type { FormDataConvertible } from '@inertiajs/core';

const page = usePage();
interface CategoryOption {
  id: string; name: string;
  owner_type: 'TENANT' | 'STORE';
  category_type: 'BUSINESS' | 'INVENTORY' | 'BOTH';
  item_type_scope: string;
  level: number;
  path: string;
}
const props = computed(() => page.props as unknown as {
  item: { id: string; item_name: string; item_type: string;
    business_category_id: string | null; inventory_category_id: string | null;
    unit: string; inventory_enabled: boolean; status: string };
  sku: null | { id: string; spec_json: Record<string, unknown>; barcode: string | null;
    sale_price_cents: number; cost_price_cents: number };
  policy: null | { inventory_track_type: string; stock_deduct_mode: string;
    allow_negative_stock: boolean; batch_required: boolean; expiry_required: boolean };
  categories: CategoryOption[];
  item_types: string[];
});

const form = ref({
  item_name: props.value.item.item_name,
  item_type: props.value.item.item_type,
  business_category_id: props.value.item.business_category_id ?? '',
  inventory_category_id: props.value.item.inventory_category_id ?? '',
  unit: props.value.item.unit,
  inventory_enabled: props.value.item.inventory_enabled,
  status: props.value.item.status,
  sku: {
    spec_json: props.value.sku?.spec_json ?? {},
    barcode: props.value.sku?.barcode ?? '',
    sale_price_cents: props.value.sku?.sale_price_cents ?? 0,
    cost_price_cents: props.value.sku?.cost_price_cents ?? 0,
  },
  policy: {
    inventory_track_type: props.value.policy?.inventory_track_type ?? 'FINISHED_GOOD',
    stock_deduct_mode: props.value.policy?.stock_deduct_mode ?? 'MANUAL_DEDUCT',
    allow_negative_stock: props.value.policy?.allow_negative_stock ?? false,
    batch_required: props.value.policy?.batch_required ?? false,
    expiry_required: props.value.policy?.expiry_required ?? false,
  },
});

const errors = ref<Record<string, string>>({});

function submit() {
  router.patch(`/tenant/items/${props.value.item.id}`,
    form.value as unknown as Record<string, FormDataConvertible>, {
      onError: (e) => { errors.value = e as Record<string, string>; },
    });
}
</script>

<template>
  <div class="p-6 max-w-3xl">
    <h1 class="text-xl font-medium mb-4">编辑物料</h1>

    <form @submit.prevent="submit" class="space-y-4 text-sm">
      <div>
        <label class="block mb-1">物料名</label>
        <input v-model="form.item_name" class="w-full px-2 py-1 border rounded" />
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block mb-1">类型</label>
          <select v-model="form.item_type" class="w-full px-2 py-1 border rounded">
            <option v-for="t in props.item_types" :key="t" :value="t">{{ t }}</option>
          </select>
        </div>
        <div>
          <label class="block mb-1">状态</label>
          <select v-model="form.status" class="w-full px-2 py-1 border rounded">
            <option value="active">在售</option>
            <option value="off_shelf">下架</option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block mb-1">经营分类</label>
          <select v-model="form.business_category_id" class="w-full px-2 py-1 border rounded">
            <option value="">不选</option>
            <option v-for="c in props.categories.filter(c =>
              (c.category_type === 'BUSINESS' || c.category_type === 'BOTH')
              && (c.item_type_scope === 'ALL' || c.item_type_scope === form.item_type))"
              :key="c.id" :value="c.id">
              {{ '│ '.repeat(c.level - 1) }}{{ c.name }}{{ c.owner_type === 'STORE' ? ' (门店)' : '' }}
            </option>
          </select>
          <div v-if="errors.business_category_id" class="text-rose-600 text-xs mt-1">{{ errors.business_category_id }}</div>
        </div>
        <div>
          <label class="block mb-1">库存分类</label>
          <select v-model="form.inventory_category_id" class="w-full px-2 py-1 border rounded">
            <option value="">不选</option>
            <option v-for="c in props.categories.filter(c =>
              (c.category_type === 'INVENTORY' || c.category_type === 'BOTH')
              && (c.item_type_scope === 'ALL' || c.item_type_scope === form.item_type))"
              :key="c.id" :value="c.id">
              {{ '│ '.repeat(c.level - 1) }}{{ c.name }}{{ c.owner_type === 'STORE' ? ' (门店)' : '' }}
            </option>
          </select>
          <div v-if="errors.inventory_category_id" class="text-rose-600 text-xs mt-1">{{ errors.inventory_category_id }}</div>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block mb-1">单位</label>
          <input v-model="form.unit" class="w-full px-2 py-1 border rounded" />
        </div>
        <div class="flex items-center gap-2">
          <input id="inv" type="checkbox" v-model="form.inventory_enabled" />
          <label for="inv">启用库存</label>
        </div>
      </div>

      <fieldset class="border rounded p-3">
        <legend class="px-2">SKU</legend>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="block mb-1">条码</label>
            <input v-model="form.sku.barcode" class="w-full px-2 py-1 border rounded" /></div>
          <div><label class="block mb-1">售价（分）</label>
            <input type="number" v-model.number="form.sku.sale_price_cents" class="w-full px-2 py-1 border rounded" /></div>
          <div><label class="block mb-1">成本（分）</label>
            <input type="number" v-model.number="form.sku.cost_price_cents" class="w-full px-2 py-1 border rounded" /></div>
        </div>
      </fieldset>

      <fieldset class="border rounded p-3">
        <legend class="px-2">库存策略</legend>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="block mb-1">追踪类型</label>
            <select v-model="form.policy.inventory_track_type" class="w-full px-2 py-1 border rounded">
              <option value="NONE">不记库存</option>
              <option value="FINISHED_GOOD">成品</option>
              <option value="RAW_MATERIAL">原料</option>
              <option value="BOTH">兼用</option>
            </select></div>
          <div><label class="block mb-1">扣减方式</label>
            <select v-model="form.policy.stock_deduct_mode" class="w-full px-2 py-1 border rounded">
              <option value="MANUAL_DEDUCT">手动扣减</option>
              <option value="SALE_DEDUCT">销售扣减</option>
              <option value="PRODUCTION_DEDUCT">生产扣减</option>
            </select></div>
        </div>
        <div class="flex gap-4 mt-3">
          <label class="flex items-center gap-1">
            <input type="checkbox" v-model="form.policy.allow_negative_stock" />允许负库存
          </label>
          <label class="flex items-center gap-1">
            <input type="checkbox" v-model="form.policy.batch_required" />要求批次
          </label>
          <label class="flex items-center gap-1">
            <input type="checkbox" v-model="form.policy.expiry_required" />要求保质期
          </label>
        </div>
      </fieldset>

      <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded">保存</button>
    </form>
  </div>
</template>
