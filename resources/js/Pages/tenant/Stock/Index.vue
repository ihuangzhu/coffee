<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ElButton, ElInput, ElEmpty } from 'element-plus';
import TenantLayout from '@/layouts/TenantLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DataTable from '@/components/DataTable.vue';

defineOptions({ layout: TenantLayout });

interface Row extends Record<string, unknown> {
  id: string; sku_id: string; item_name: string; unit: string;
  barcode: string | null;
  available_qty: number; reserved_qty: number;
  in_transit_qty: number; damaged_qty: number;
  updated_at: string | null;
}

const page = usePage();
const props = computed(() => page.props as unknown as {
  store_id: string; rows: Row[]; total: number;
  page: number; pageSize: number; q: string;
});

const storeId = computed(() => props.value.store_id);
const keyword = ref(props.value.q);

function reload(params: Record<string, unknown> = {}) {
  router.get('/tenant/stock', {
    q: keyword.value,
    page: props.value.page, per_page: props.value.pageSize, ...params,
  }, { preserveState: true, preserveScroll: true });
}

function onPage(n: number) { reload({ page: n }); }
function onPageSize(n: number) { reload({ page: 1, per_page: n }); }
function onSearch() { reload({ page: 1 }); }

const columns = [
  { key: 'item_name', label: '物料', minWidth: 200 },
  { key: 'barcode', label: '条码', width: 180,
    formatter: (r: Row) => r.barcode ?? '—' },
  { key: 'unit', label: '单位', width: 80 },
  { key: 'available_qty', label: '可用', width: 100, align: 'right' as const },
  { key: 'reserved_qty', label: '预占', width: 100, align: 'right' as const },
  { key: 'in_transit_qty', label: '在途', width: 100, align: 'right' as const },
  { key: 'damaged_qty', label: '报损中', width: 100, align: 'right' as const },
  { key: 'updated_at', label: '更新时间', width: 200,
    formatter: (r: Row) => r.updated_at ?? '' },
];
</script>

<template>
  <Head title="库存查询" />
  <PageHeader>
    <template #filter>
      <ElInput v-model="keyword" placeholder="搜索物料名" style="width: 240px"
        :disabled="!storeId" @keyup.enter="onSearch" />
      <ElButton type="primary" :disabled="!storeId" @click="onSearch">筛选</ElButton>
    </template>
  </PageHeader>

  <div v-if="!storeId" class="mt-3">
    <ElEmpty description="请在顶部切换栏选择门店" />
  </div>
  <div v-else class="mt-3">
    <DataTable
      :rows="props.rows"
      :total="props.total"
      :page="props.page"
      :page-size="props.pageSize"
      :columns="columns"
      @update:page="onPage"
      @update:pageSize="onPageSize"
    >
      <template #empty>
        <div class="py-12 text-[13px] text-center" style="color: var(--text-faint)">
          无库存记录
        </div>
      </template>
    </DataTable>
  </div>
</template>
