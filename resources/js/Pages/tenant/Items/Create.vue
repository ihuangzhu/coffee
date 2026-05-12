<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import type { FormDataConvertible } from '@inertiajs/core';
import {
  ElCard, ElForm, ElFormItem, ElInput, ElInputNumber, ElSelect, ElOption,
  ElButton, ElSwitch, ElCheckbox,
} from 'element-plus';
import TenantLayout from '@/layouts/TenantLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { itemTypeLabel } from '@/lib/itemTypes';

defineOptions({ layout: TenantLayout });

interface CategoryOption {
  id: string; name: string;
  owner_type: 'TENANT' | 'STORE';
  category_type: 'BUSINESS' | 'INVENTORY' | 'BOTH';
  item_type_scope: string;
  level: number;
  path: string;
}

const page = usePage();
const props = computed(() => page.props as unknown as {
  categories: CategoryOption[];
  item_types: string[];
});

const form = ref({
  item_name: '',
  item_type: 'SALE_PRODUCT',
  business_category_id: '' as string,
  inventory_category_id: '' as string,
  unit: 'PCS',
  inventory_enabled: true,
  sku: { spec_json: {}, barcode: '', sale_price_cents: 0, cost_price_cents: 0 },
  policy: {
    inventory_track_type: 'FINISHED_GOOD',
    stock_deduct_mode: 'MANUAL_DEDUCT',
    allow_negative_stock: false,
    batch_required: false,
    expiry_required: false,
  },
});

const businessCategoryOptions = computed(() => props.value.categories.filter((c) =>
  (c.category_type === 'BUSINESS' || c.category_type === 'BOTH')
  && (c.item_type_scope === 'ALL' || c.item_type_scope === form.value.item_type)));
const inventoryCategoryOptions = computed(() => props.value.categories.filter((c) =>
  (c.category_type === 'INVENTORY' || c.category_type === 'BOTH')
  && (c.item_type_scope === 'ALL' || c.item_type_scope === form.value.item_type)));

const errors = ref<Record<string, string>>({});
const processing = ref(false);

function submit() {
  processing.value = true;
  router.post('/tenant/items', form.value as unknown as Record<string, FormDataConvertible>, {
    onError: (e) => { errors.value = e as Record<string, string>; },
    onFinish: () => { processing.value = false; },
  });
}
</script>

<template>
  <Head title="新建商品" />
  <PageHeader :breadcrumb="[{ label: '商品管理', to: '/tenant/items' }, { label: '新建' }]">
    <template #actions>
      <ElButton @click="router.visit('/tenant/items')">取消</ElButton>
      <ElButton type="primary" :loading="processing" @click="submit">创建</ElButton>
    </template>
  </PageHeader>

  <ElCard shadow="never" class="mt-3 max-w-3xl">
    <ElForm label-position="top">
      <div class="text-[14px] font-semibold mb-3">基本信息</div>
      <ElFormItem label="商品名" :error="errors.item_name" required>
        <ElInput v-model="form.item_name" maxlength="100" />
      </ElFormItem>
      <div class="grid grid-cols-3 gap-4">
        <ElFormItem label="类型">
          <ElSelect v-model="form.item_type" style="width: 100%">
            <ElOption v-for="t in props.item_types" :key="t" :value="t" :label="itemTypeLabel(t)" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="经营分类" :error="errors.business_category_id">
          <ElSelect v-model="form.business_category_id" clearable filterable style="width: 100%">
            <ElOption v-for="c in businessCategoryOptions" :key="c.id"
              :value="c.id"
              :label="'│ '.repeat(c.level - 1) + c.name + (c.owner_type === 'STORE' ? ' (门店)' : '')" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="库存分类" :error="errors.inventory_category_id">
          <ElSelect v-model="form.inventory_category_id" clearable filterable style="width: 100%">
            <ElOption v-for="c in inventoryCategoryOptions" :key="c.id"
              :value="c.id"
              :label="'│ '.repeat(c.level - 1) + c.name + (c.owner_type === 'STORE' ? ' (门店)' : '')" />
          </ElSelect>
        </ElFormItem>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <ElFormItem label="单位">
          <ElInput v-model="form.unit" />
        </ElFormItem>
        <ElFormItem label="启用库存">
          <ElSwitch v-model="form.inventory_enabled" />
        </ElFormItem>
      </div>
    </ElForm>
  </ElCard>

  <ElCard shadow="never" class="mt-4 max-w-3xl">
    <template #header><span class="text-[14px] font-semibold">SKU</span></template>
    <ElForm label-position="top">
      <div class="grid grid-cols-2 gap-4">
        <ElFormItem label="条码">
          <ElInput v-model="form.sku.barcode" />
        </ElFormItem>
        <ElFormItem label="售价（分）">
          <ElInputNumber v-model="form.sku.sale_price_cents" :min="0" controls-position="right" style="width: 100%" />
        </ElFormItem>
        <ElFormItem label="成本（分）">
          <ElInputNumber v-model="form.sku.cost_price_cents" :min="0" controls-position="right" style="width: 100%" />
        </ElFormItem>
      </div>
    </ElForm>
  </ElCard>

  <ElCard shadow="never" class="mt-4 max-w-3xl">
    <template #header><span class="text-[14px] font-semibold">库存策略</span></template>
    <ElForm label-position="top">
      <div class="grid grid-cols-2 gap-4">
        <ElFormItem label="追踪类型">
          <ElSelect v-model="form.policy.inventory_track_type" style="width: 100%">
            <ElOption label="不记库存" value="NONE" />
            <ElOption label="成品" value="FINISHED_GOOD" />
            <ElOption label="原料" value="RAW_MATERIAL" />
            <ElOption label="兼用" value="BOTH" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="扣减方式">
          <ElSelect v-model="form.policy.stock_deduct_mode" style="width: 100%">
            <ElOption label="手动扣减" value="MANUAL_DEDUCT" />
            <ElOption label="销售扣减" value="SALE_DEDUCT" />
            <ElOption label="生产扣减" value="PRODUCTION_DEDUCT" />
          </ElSelect>
        </ElFormItem>
      </div>
      <div class="flex items-center gap-6 mt-2">
        <ElCheckbox v-model="form.policy.allow_negative_stock">允许负库存</ElCheckbox>
        <ElCheckbox v-model="form.policy.batch_required">要求批次</ElCheckbox>
        <ElCheckbox v-model="form.policy.expiry_required">要求保质期</ElCheckbox>
      </div>
    </ElForm>
  </ElCard>
</template>
