<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const page = usePage();
const props = computed(() => page.props as unknown as {
  stores: { id: string; name: string }[];
  skus: { id: string; item_name: string; unit: string; barcode: string | null }[];
});

const form = ref({ store_id: '', sku_id: '', qty: 0, unit_cost_cents: 0, reason: '' });
const errors = ref<Record<string, string>>({});

function submit() {
  router.post('/tenant/stock/damage', form.value, {
    onError: (e) => { errors.value = e as Record<string, string>; },
  });
}
</script>

<template>
  <div class="p-6 max-w-xl">
    <h1 class="text-xl font-medium mb-4">报损登记</h1>

    <form @submit.prevent="submit" class="space-y-4 text-sm">
      <div><label class="block mb-1">门店</label>
        <select v-model="form.store_id" class="w-full px-2 py-1 border rounded">
          <option value="">请选择</option>
          <option v-for="s in props.stores" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select></div>
      <div><label class="block mb-1">SKU</label>
        <select v-model="form.sku_id" class="w-full px-2 py-1 border rounded">
          <option value="">请选择</option>
          <option v-for="s in props.skus" :key="s.id" :value="s.id">
            {{ s.item_name }} ({{ s.unit }})
          </option>
        </select></div>
      <div class="grid grid-cols-2 gap-4">
        <div><label class="block mb-1">数量</label>
          <input type="number" step="0.0001" v-model.number="form.qty"
            class="w-full px-2 py-1 border rounded" /></div>
        <div><label class="block mb-1">单价（分）</label>
          <input type="number" v-model.number="form.unit_cost_cents"
            class="w-full px-2 py-1 border rounded" /></div>
      </div>
      <div><label class="block mb-1">原因</label>
        <input v-model="form.reason" class="w-full px-2 py-1 border rounded" /></div>

      <button type="submit" class="px-4 py-2 bg-rose-600 text-white rounded">提交报损</button>
    </form>
  </div>
</template>
