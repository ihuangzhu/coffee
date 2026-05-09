<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

interface Row {
  id: string; sku_id: string; item_name: string; unit: string;
  barcode: string | null;
  available_qty: number; reserved_qty: number;
  in_transit_qty: number; damaged_qty: number;
  updated_at: string | null;
}

const page = usePage();
const props = computed(() => page.props as unknown as {
  stores: { id: string; name: string }[];
  store_id: string; rows: Row[]; total: number;
  page: number; pageSize: number; q: string;
});

const storeId = ref(props.value.store_id);
const keyword = ref(props.value.q);

function reload(params: Record<string, unknown> = {}) {
  router.get('/tenant/stock', {
    store_id: storeId.value, q: keyword.value,
    page: props.value.page, per_page: props.value.pageSize, ...params,
  }, { preserveState: true, preserveScroll: true });
}
</script>

<template>
  <div class="p-6">
    <h1 class="text-xl font-medium mb-4">库存查询</h1>

    <div class="flex gap-2 mb-3 text-sm">
      <select v-model="storeId" class="px-2 py-1 border rounded"
        @change="reload({ page: 1 })">
        <option value="">选择门店</option>
        <option v-for="s in props.stores" :key="s.id" :value="s.id">{{ s.name }}</option>
      </select>
      <input v-model="keyword" placeholder="搜索物料名"
        class="px-2 py-1 border rounded w-60" :disabled="!storeId"
        @keyup.enter="reload({ page: 1 })" />
    </div>

    <div v-if="!storeId" class="text-slate-400 py-12 text-center">
      请先选择门店
    </div>
    <table v-else class="w-full text-sm border-collapse">
      <thead>
        <tr class="border-b bg-slate-50">
          <th class="text-left p-2">物料</th>
          <th class="text-left p-2">条码</th>
          <th class="text-left p-2">单位</th>
          <th class="text-right p-2">可用</th>
          <th class="text-right p-2">预占</th>
          <th class="text-right p-2">在途</th>
          <th class="text-right p-2">报损中</th>
          <th class="text-left p-2">更新时间</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="r in props.rows" :key="r.id" class="border-b">
          <td class="p-2">{{ r.item_name }}</td>
          <td class="p-2 font-mono text-xs">{{ r.barcode || '—' }}</td>
          <td class="p-2">{{ r.unit }}</td>
          <td class="p-2 text-right font-medium">{{ r.available_qty }}</td>
          <td class="p-2 text-right text-slate-400">{{ r.reserved_qty }}</td>
          <td class="p-2 text-right text-slate-400">{{ r.in_transit_qty }}</td>
          <td class="p-2 text-right text-slate-400">{{ r.damaged_qty }}</td>
          <td class="p-2 text-xs text-slate-500">{{ r.updated_at }}</td>
        </tr>
        <tr v-if="props.rows.length === 0">
          <td colspan="8" class="text-center text-slate-400 p-6">无库存记录</td>
        </tr>
      </tbody>
    </table>

    <div class="mt-4 flex items-center justify-between text-sm">
      <span>共 {{ props.total }} 条</span>
      <div class="flex gap-1">
        <button :disabled="props.page <= 1"
          class="px-2 py-1 border rounded disabled:opacity-40"
          @click="reload({ page: props.page - 1 })">上一页</button>
        <span class="px-2 py-1">{{ props.page }}</span>
        <button :disabled="props.page * props.pageSize >= props.total"
          class="px-2 py-1 border rounded disabled:opacity-40"
          @click="reload({ page: props.page + 1 })">下一页</button>
      </div>
    </div>
  </div>
</template>
