<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
  ElButton, ElInput, ElSelect, ElOption, ElTag, ElAlert,
} from 'element-plus';
import TenantLayout from '@/layouts/TenantLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DataTable from '@/components/DataTable.vue';
import { itemTypeLabel } from '@/lib/itemTypes';

defineOptions({ layout: TenantLayout });

interface Row extends Record<string, unknown> {
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

function onPage(n: number) { reload({ page: n }); }
function onPageSize(n: number) { reload({ page: 1, per_page: n }); }
function onSearch() { reload({ page: 1 }); }
function reset() {
  keyword.value = ''; itemType.value = 'all'; statusFilter.value = 'all';
  reload({ page: 1, q: '', item_type: 'all', status: 'all' });
}

function goCreate() { router.visit('/tenant/items/create'); }
function goEdit(row: Row) { router.visit(`/tenant/items/${row.id}/edit`); }

function priceYuan(cents: number): string { return (cents / 100).toFixed(2); }

const columns = [
  { key: 'item_name', label: '名称', minWidth: 180 },
  { key: 'item_type', label: '类型', width: 130, formatter: (r: Row) => itemTypeLabel(r.item_type) },
  { key: 'business_category_name', label: '经营分类', width: 160,
    formatter: (r: Row) => r.business_category_name ?? '—' },
  { key: 'inventory_category_name', label: '库存分类', width: 160,
    formatter: (r: Row) => r.inventory_category_name ?? '—' },
  { key: 'unit', label: '单位', width: 80 },
  { key: 'sku_count', label: 'SKU 数', width: 90, align: 'right' as const },
  { key: 'price', label: '起售价', width: 110, align: 'right' as const,
    formatter: (r: Row) => `¥${priceYuan(r.first_sku_price_cents)}` },
  { key: 'inventory_enabled', label: '库存', width: 80, align: 'center' as const },
  { key: 'status', label: '状态', width: 90, align: 'center' as const },
];
</script>

<template>
  <Head title="商品管理" />
  <PageHeader>
    <template #filter>
      <ElInput v-model="keyword" placeholder="搜索商品名" style="width: 240px"
        @keyup.enter="onSearch" />
      <ElSelect v-model="itemType" style="width: 140px" @change="onSearch">
        <ElOption label="全部类型" value="all" />
        <ElOption v-for="t in props.item_types" :key="t" :value="t" :label="itemTypeLabel(t)" />
      </ElSelect>
      <ElSelect v-model="statusFilter" style="width: 120px" @change="onSearch">
        <ElOption label="全部状态" value="all" />
        <ElOption label="在售" value="active" />
        <ElOption label="下架" value="off_shelf" />
      </ElSelect>
      <ElButton type="primary" @click="onSearch">筛选</ElButton>
      <ElButton @click="reset">重置</ElButton>
    </template>
    <template #actions>
      <ElButton type="primary" :disabled="!props.has_categories" @click="goCreate">
        + 新建商品
      </ElButton>
    </template>
  </PageHeader>

  <ElAlert v-if="!props.has_categories" type="warning" show-icon :closable="false"
           class="!mt-3"
           title="请先到「商品分类」创建至少一个分类后再新建商品。" />

  <div class="mt-3">
    <DataTable
      :rows="props.rows"
      :total="props.total"
      :page="props.page"
      :page-size="props.pageSize"
      :columns="columns"
      :actions-width="80"
      @update:page="onPage"
      @update:pageSize="onPageSize"
    >
      <template #col-inventory_enabled="{ row }">
        <ElTag size="small" :type="(row as Row).inventory_enabled ? 'success' : 'info'">
          {{ (row as Row).inventory_enabled ? '是' : '否' }}
        </ElTag>
      </template>
      <template #col-status="{ row }">
        <ElTag size="small" :type="(row as Row).status === 'active' ? 'success' : 'info'">
          {{ (row as Row).status === 'active' ? '在售' : '下架' }}
        </ElTag>
      </template>
      <template #actions="{ row }">
        <ElButton link size="small" @click="goEdit(row as Row)">编辑</ElButton>
      </template>
    </DataTable>
  </div>
</template>
