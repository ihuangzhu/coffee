<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
  ElCard, ElForm, ElFormItem, ElInput, ElInputNumber, ElSelect, ElOption, ElButton,
} from 'element-plus';
import TenantLayout from '@/layouts/TenantLayout.vue';
import PageHeader from '@/components/PageHeader.vue';

defineOptions({ layout: TenantLayout });

const page = usePage();
const props = computed(() => page.props as unknown as {
  stores: { id: string; name: string }[];
  skus: { id: string; item_name: string; unit: string; barcode: string | null }[];
});

const form = ref({ store_id: '', sku_id: '', actual_qty: 0, note: '' });
const errors = ref<Record<string, string>>({});
const processing = ref(false);

function submit() {
  processing.value = true;
  router.post('/tenant/stock/stocktake', form.value, {
    onError: (e) => { errors.value = e as Record<string, string>; },
    onFinish: () => { processing.value = false; },
  });
}
</script>

<template>
  <Head title="盘点" />
  <PageHeader :breadcrumb="[{ label: '库存查询', to: '/tenant/stock' }, { label: '单 SKU 盘点' }]">
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
            :label="`${s.item_name} (${s.unit})`" />
        </ElSelect>
      </ElFormItem>
      <ElFormItem label="实盘数量" :error="errors.actual_qty">
        <ElInputNumber v-model="form.actual_qty" :precision="4" :min="0" controls-position="right" style="width: 100%" />
      </ElFormItem>
      <ElFormItem label="备注">
        <ElInput v-model="form.note" />
      </ElFormItem>
    </ElForm>
  </ElCard>
</template>
