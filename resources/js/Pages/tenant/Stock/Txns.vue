<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

interface Row {
  id: number; biz_type: string; direction: string;
  qty_change: number; sku_id: string; item_name: string;
  occurred_at: string | null;
  unit_cost_cents: number | null; amount_cents: number | null;
  meta: Record<string, unknown> | null;
  is_cancelled: boolean; is_reversal: boolean;
}

const page = usePage();
const props = computed(() => page.props as unknown as {
  rows: Row[]; total: number; page: number; pageSize: number;
  biz_type: string; sku_id: string; biz_types: string[];
});

const bizType = ref(props.value.biz_type);

function reload(params: Record<string, unknown> = {}) {
  router.get('/tenant/stock/txns', {
    biz_type: bizType.value, sku_id: props.value.sku_id,
    page: props.value.page, per_page: props.value.pageSize, ...params,
  }, { preserveState: true, preserveScroll: true });
}

function reverse(id: number) {
  if (!confirm(`确认撤销流水 #${id}？`)) return;
  router.post(`/tenant/stock/txns/${id}/reverse`, {},
    { preserveScroll: true, onSuccess: () => reload() });
}
</script>

<template>
  <div class="p-6">
    <h1 class="text-xl font-medium mb-4">库存流水</h1>

    <div class="flex gap-2 mb-3 text-sm">
      <select v-model="bizType" class="px-2 py-1 border rounded"
        @change="reload({ page: 1 })">
        <option value="all">全部类型</option>
        <option v-for="t in props.biz_types" :key="t" :value="t">{{ t }}</option>
      </select>
    </div>

    <table class="w-full text-sm border-collapse">
      <thead>
        <tr class="border-b bg-slate-50">
          <th class="text-left p-2">#</th>
          <th class="text-left p-2">类型</th>
          <th class="text-left p-2">方向</th>
          <th class="text-right p-2">数量</th>
          <th class="text-left p-2">物料</th>
          <th class="text-left p-2">时间</th>
          <th class="text-left p-2">状态</th>
          <th class="text-right p-2">操作</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="r in props.rows" :key="r.id"
          :class="['border-b', r.is_cancelled ? 'opacity-50 line-through' : '']">
          <td class="p-2 font-mono">{{ r.id }}</td>
          <td class="p-2">{{ r.biz_type }}</td>
          <td class="p-2">{{ r.direction }}</td>
          <td class="p-2 text-right">{{ r.qty_change }}</td>
          <td class="p-2">{{ r.item_name }}</td>
          <td class="p-2 text-xs text-slate-500">{{ r.occurred_at }}</td>
          <td class="p-2">
            <span v-if="r.is_reversal" class="text-amber-600 text-xs">撤销笔</span>
            <span v-else-if="r.is_cancelled" class="text-slate-400 text-xs">已撤销</span>
            <span v-else class="text-emerald-600 text-xs">有效</span>
          </td>
          <td class="p-2 text-right">
            <button v-if="!r.is_cancelled && !r.is_reversal"
              class="text-rose-600 text-xs"
              @click="reverse(r.id)">撤销</button>
          </td>
        </tr>
        <tr v-if="props.rows.length === 0">
          <td colspan="8" class="text-center text-slate-400 p-6">暂无流水</td>
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
