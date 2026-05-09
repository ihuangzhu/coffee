<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';

interface Row {
  id: string; item_name: string; item_type: string;
  business_category_id: string | null; business_category_name: string | null;
  inventory_category_id: string | null; inventory_category_name: string | null;
  unit: string; sku_count: number; first_sku_price_cents: number;
  inventory_enabled: boolean; status: string; created_at: string | null;
}

const page = usePage();
const props = computed(() => page.props as unknown as {
  rows: Row[]; total: number; page: number; pageSize: number;
  q: string; item_type: string; status: string;
  has_categories: boolean; item_types: string[];
});

const keyword = ref(props.value.q);
const itemType = ref(props.value.item_type);
const statusFilter = ref(props.value.status);

function reload(params: Record<string, unknown> = {}) {
  router.get('/tenant/items', {
    page: props.value.page, per_page: props.value.pageSize,
    q: keyword.value, item_type: itemType.value, status: statusFilter.value,
    ...params,
  }, { preserveState: true, preserveScroll: true });
}

function priceYuan(cents: number): string {
  return (cents / 100).toFixed(2);
}
</script>

<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-xl font-medium">物料管理</h1>
      <Link href="/tenant/items/create"
        class="px-3 py-1.5 bg-indigo-600 text-white text-sm rounded">
        新建物料
      </Link>
    </div>

    <div v-if="!props.has_categories"
      class="mb-4 px-3 py-2 bg-amber-50 border border-amber-200 text-sm rounded">
      请先到「分类管理」创建至少一个分类后再新建物料。
    </div>

    <div class="flex gap-2 mb-3">
      <input v-model="keyword" placeholder="搜索物料名"
        class="px-2 py-1 border rounded text-sm w-60"
        @keyup.enter="reload({ page: 1 })" />
      <select v-model="itemType" class="px-2 py-1 border rounded text-sm"
        @change="reload({ page: 1 })">
        <option value="all">全部类型</option>
        <option v-for="t in props.item_types" :key="t" :value="t">{{ t }}</option>
      </select>
      <select v-model="statusFilter" class="px-2 py-1 border rounded text-sm"
        @change="reload({ page: 1 })">
        <option value="all">全部状态</option>
        <option value="active">在售</option>
        <option value="off_shelf">下架</option>
      </select>
    </div>

    <table class="w-full text-sm border-collapse">
      <thead>
        <tr class="border-b bg-slate-50">
          <th class="text-left p-2">名称</th>
          <th class="text-left p-2">类型</th>
          <th class="text-left p-2">经营分类</th>
          <th class="text-left p-2">库存分类</th>
          <th class="text-left p-2">单位</th>
          <th class="text-right p-2">SKU 数</th>
          <th class="text-right p-2">起售价</th>
          <th class="text-center p-2">库存</th>
          <th class="text-center p-2">状态</th>
          <th class="text-right p-2">操作</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="r in props.rows" :key="r.id" class="border-b hover:bg-slate-50">
          <td class="p-2">{{ r.item_name }}</td>
          <td class="p-2">{{ r.item_type }}</td>
          <td class="p-2">{{ r.business_category_name || '—' }}</td>
          <td class="p-2">{{ r.inventory_category_name || '—' }}</td>
          <td class="p-2">{{ r.unit }}</td>
          <td class="p-2 text-right">{{ r.sku_count }}</td>
          <td class="p-2 text-right">¥{{ priceYuan(r.first_sku_price_cents) }}</td>
          <td class="p-2 text-center">
            <span v-if="r.inventory_enabled" class="text-emerald-600">是</span>
            <span v-else class="text-slate-400">否</span>
          </td>
          <td class="p-2 text-center">
            <span :class="r.status === 'active' ? 'text-emerald-600' : 'text-slate-400'">
              {{ r.status === 'active' ? '在售' : '下架' }}
            </span>
          </td>
          <td class="p-2 text-right">
            <Link :href="`/tenant/items/${r.id}/edit`" class="text-indigo-600">编辑</Link>
          </td>
        </tr>
        <tr v-if="props.rows.length === 0">
          <td colspan="10" class="text-center text-slate-400 p-6">暂无物料</td>
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
