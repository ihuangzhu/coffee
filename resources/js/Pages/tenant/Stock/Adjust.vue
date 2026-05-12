<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
  ElCard, ElForm, ElFormItem, ElSelect, ElOption, ElInputNumber, ElButton,
} from 'element-plus';
import TenantLayout from '@/layouts/TenantLayout.vue';
import PageHeader from '@/components/PageHeader.vue';

defineOptions({ layout: TenantLayout });

const page = usePage();
const props = computed(() => page.props as unknown as {
  stores: { id: string; name: string }[];
  skus: { id: string; item_name: string; unit: string; barcode: string | null }[];
});

const form = ref({
  store_id: '', sku_id: '',
  qty_change: 0, direction: 'IN' as 'IN' | 'OUT',
  subtype: 'MANUAL' as 'INITIAL' | 'MANUAL',
});
const errors = ref<Record<string, string>>({});
const processing = ref(false);

function submit() {
  processing.value = true;
  const v = Math.abs(form.value.qty_change);
  const signed = form.value.direction === 'OUT' ? -v : v;
  router.post('/tenant/stock/adjust', { ...form.value, qty_change: signed }, {
    onError: (e) => { errors.value = e as Record<string, string>; },
    onFinish: () => { processing.value = false; },
  });
}
</script>

<template>
  <Head title="库存调整" />
  <PageHeader :breadcrumb="[{ label: '库存查询', to: '/tenant/stock' }, { label: '手动调整' }]">
    <template #actions>
      <ElButton @click="router.visit('/tenant/stock')">取消</ElButton>
      <ElButton type="primary" :loading="processing" @click="submit">提交</ElButton>
    </template>
  </PageHeader>

  <ElCard shadow="never" class="mt-3 max-w-2xl">
    <ElForm label-position="top">
      <ElFormItem label="门店" :error="errors.store_id">
        <ElSelect v-model="form.store_id" placeholder="请选择" style="width: 100%">
          <ElOption v-for="s in props.stores" :key="s.id" :value="s.id" :label="s.name" />
        </ElSelect>
      </ElFormItem>
      <ElFormItem label="SKU" :error="errors.sku_id">
        <ElSelect v-model="form.sku_id" placeholder="请选择" filterable style="width: 100%">
          <ElOption v-for="s in props.skus" :key="s.id" :value="s.id"
            :label="`${s.item_name} (${s.unit})${s.barcode ? ' · ' + s.barcode : ''}`" />
        </ElSelect>
      </ElFormItem>
      <div class="grid grid-cols-3 gap-4">
        <ElFormItem label="数量" :error="errors.qty_change">
          <ElInputNumber v-model="form.qty_change" :precision="4" controls-position="right" style="width: 100%" />
        </ElFormItem>
        <ElFormItem label="方向">
          <ElSelect v-model="form.direction" style="width: 100%">
            <ElOption label="入库" value="IN" />
            <ElOption label="出库" value="OUT" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="子类型">
          <ElSelect v-model="form.subtype" style="width: 100%">
            <ElOption label="初始化" value="INITIAL" />
            <ElOption label="手动" value="MANUAL" />
          </ElSelect>
        </ElFormItem>
      </div>
    </ElForm>
  </ElCard>
</template>
