<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

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

function submit() {
  // qty_change 应当与 direction 同号；前端做一次纠正
  const v = Math.abs(form.value.qty_change);
  const signed = form.value.direction === 'OUT' ? -v : v;
  router.post('/tenant/stock/adjust', { ...form.value, qty_change: signed }, {
    onError: (e) => { errors.value = e as Record<string, string>; },
  });
}
</script>

<template>
  <div class="p-6 max-w-xl">
    <h1 class="text-xl font-medium mb-4">手动调整库存</h1>

    <form @submit.prevent="submit" class="space-y-4 text-sm">
      <div>
        <label class="block mb-1">门店</label>
        <select v-model="form.store_id" class="w-full px-2 py-1 border rounded">
          <option value="">请选择</option>
          <option v-for="s in props.stores" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
      </div>

      <div>
        <label class="block mb-1">SKU</label>
        <select v-model="form.sku_id" class="w-full px-2 py-1 border rounded">
          <option value="">请选择</option>
          <option v-for="s in props.skus" :key="s.id" :value="s.id">
            {{ s.item_name }} ({{ s.unit }}) {{ s.barcode ? '· ' + s.barcode : '' }}
          </option>
        </select>
      </div>

      <div class="grid grid-cols-3 gap-4">
        <div>
          <label class="block mb-1">数量</label>
          <input type="number" step="0.0001" v-model.number="form.qty_change"
            class="w-full px-2 py-1 border rounded" />
        </div>
        <div>
          <label class="block mb-1">方向</label>
          <select v-model="form.direction" class="w-full px-2 py-1 border rounded">
            <option value="IN">入库</option>
            <option value="OUT">出库</option>
          </select>
        </div>
        <div>
          <label class="block mb-1">子类型</label>
          <select v-model="form.subtype" class="w-full px-2 py-1 border rounded">
            <option value="INITIAL">初始化</option>
            <option value="MANUAL">手动</option>
          </select>
        </div>
      </div>

      <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded">提交</button>
    </form>
  </div>
</template>
