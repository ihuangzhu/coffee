<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
  ElCard, ElForm, ElFormItem, ElSwitch, ElSelect, ElOption, ElButton,
} from 'element-plus';
import TenantLayout from '@/layouts/TenantLayout.vue';
import PageHeader from '@/components/PageHeader.vue';

defineOptions({ layout: TenantLayout });

interface Cfg {
  inventory_enabled: boolean; multi_location_enabled: boolean;
  production_enabled: boolean; purchase_enabled: boolean;
  transfer_enabled: boolean; stocktaking_enabled: boolean;
  negative_stock_allowed: boolean; inventory_cost_method: string;
  expiry_management_enabled: boolean; batch_management_enabled: boolean;
  auto_deduct_raw_material_enabled: boolean;
}

const page = usePage();
const props = computed(() => page.props as unknown as { config: Cfg });
const form = ref<Cfg>({ ...props.value.config });
const processing = ref(false);

function submit() {
  processing.value = true;
  router.patch('/tenant/settings/inventory', form.value, {
    onFinish: () => { processing.value = false; },
  });
}

const switches: Array<[keyof Cfg, string, string]> = [
  ['inventory_enabled', '总开关', '是否启用库存模块'],
  ['multi_location_enabled', '多货架/多库位', '启用多货架/多库位管理'],
  ['production_enabled', '自制/生产', '启用自制商品与生产流程'],
  ['purchase_enabled', '采购', '启用采购流程'],
  ['transfer_enabled', '调拨', '启用门店间调拨'],
  ['stocktaking_enabled', '盘点', '启用盘点功能'],
  ['negative_stock_allowed', '允许负库存', '与 SKU policy 取 AND'],
  ['expiry_management_enabled', '保质期', '启用保质期管理'],
  ['batch_management_enabled', '批次', '启用批次管理'],
  ['auto_deduct_raw_material_enabled', '销售扣减原料', '需要 BOM 配置'],
];
</script>

<template>
  <Head title="库存配置" />
  <PageHeader>
    <template #actions>
      <ElButton type="primary" :loading="processing" @click="submit">保存</ElButton>
    </template>
  </PageHeader>

  <ElCard shadow="never" class="mt-3 max-w-3xl">
    <ElForm label-position="left" label-width="180px">
      <ElFormItem v-for="[k, label, desc] in switches" :key="k">
        <template #label>
          <div class="leading-tight">
            <div class="text-[13px]">{{ label }}</div>
            <div class="text-[12px]" style="color: var(--text-muted)">{{ desc }}</div>
          </div>
        </template>
        <ElSwitch v-model="form[k] as boolean" />
      </ElFormItem>
      <ElFormItem label="成本核算方法">
        <ElSelect v-model="form.inventory_cost_method" style="width: 240px">
          <ElOption label="FIFO（先进先出）" value="FIFO" />
          <ElOption label="移动加权平均" value="MOVING_AVG" />
          <ElOption label="标准成本" value="STANDARD" />
        </ElSelect>
      </ElFormItem>
    </ElForm>
  </ElCard>
</template>
