<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

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

function submit() {
  router.patch('/tenant/settings/inventory', form.value);
}

const switches = [
  ['inventory_enabled', '总开关：是否启用库存模块'],
  ['multi_location_enabled', '启用多货架/多库位'],
  ['production_enabled', '启用自制商品/生产'],
  ['purchase_enabled', '启用采购'],
  ['transfer_enabled', '启用调拨'],
  ['stocktaking_enabled', '启用盘点'],
  ['negative_stock_allowed', '允许负库存（与 SKU policy 两层 AND）'],
  ['expiry_management_enabled', '启用保质期'],
  ['batch_management_enabled', '启用批次'],
  ['auto_deduct_raw_material_enabled', '销售时自动扣减原料（需 BOM）'],
] as const;
</script>

<template>
  <div class="p-6 max-w-3xl">
    <h1 class="text-xl font-medium mb-4">租户库存配置</h1>

    <form @submit.prevent="submit" class="space-y-3 text-sm">
      <div v-for="[k, label] in switches" :key="k"
        class="flex items-center justify-between border-b py-2">
        <span>{{ label }}</span>
        <label class="inline-flex items-center gap-2">
          <input type="checkbox" v-model="(form as any)[k]" />
          <span class="text-xs text-slate-500">{{ (form as any)[k] ? '启用' : '关闭' }}</span>
        </label>
      </div>

      <div class="flex items-center justify-between border-b py-2">
        <span>成本核算方法</span>
        <select v-model="form.inventory_cost_method" class="px-2 py-1 border rounded">
          <option value="FIFO">FIFO（先进先出）</option>
          <option value="MOVING_AVG">移动加权平均</option>
          <option value="STANDARD">标准成本</option>
        </select>
      </div>

      <button type="submit"
        class="mt-4 px-4 py-2 bg-indigo-600 text-white rounded">保存</button>
    </form>
  </div>
</template>
