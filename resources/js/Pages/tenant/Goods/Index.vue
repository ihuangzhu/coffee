<script setup lang="ts">
/**
 * 租户后台 - 商品列表。
 * 支持 name 模糊 q + status 三选过滤；行级 actions：编辑 / 上架·下架。
 */
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { ElButton, ElInput, ElMessageBox, ElOption, ElSelect, ElTag } from 'element-plus';
import TenantLayout from '@/layouts/TenantLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DataTable from '@/components/DataTable.vue';

defineOptions({ layout: TenantLayout });

interface GoodsRow extends Record<string, unknown> {
  id: string;
  name: string;
  category_id: string;
  category_name: string | null;
  sku_base_price_cents: number;
  sku_code: string | null;
  status: 'active' | 'off_shelf';
  created_at: string | null;
}

const page = usePage();
const props = computed(() => page.props as unknown as {
  rows: GoodsRow[];
  total: number;
  page: number;
  pageSize: number;
  q: string;
  status: 'all' | 'active' | 'off_shelf';
  has_categories: boolean;
});

const keyword = ref(props.value.q);
const statusFilter = ref<'all' | 'active' | 'off_shelf'>(props.value.status);

function reload(params: Record<string, unknown> = {}) {
  router.get('/tenant/goods', {
    page: props.value.page,
    per_page: props.value.pageSize,
    q: keyword.value,
    status: statusFilter.value,
    ...params,
  }, { preserveState: true, preserveScroll: true });
}

function onPage(n: number) { reload({ page: n }); }
function onPageSize(n: number) { reload({ page: 1, per_page: n }); }
function onSearch() { reload({ page: 1 }); }
function reset() {
  keyword.value = '';
  statusFilter.value = 'all';
  reload({ page: 1, q: '', status: 'all' });
}

function goCreate() { router.visit('/tenant/goods/create'); }
function goEdit(row: GoodsRow) { router.visit(`/tenant/goods/${row.id}/edit`); }
function goCategories() { router.visit('/tenant/categories'); }

async function toggleStatus(row: GoodsRow) {
  const next = row.status === 'active' ? 'off_shelf' : 'active';
  const verb = next === 'active' ? '上架' : '下架';
  try {
    await ElMessageBox.confirm(`确认${verb}商品「${row.name}」？`, `${verb}商品`,
      { confirmButtonText: verb, cancelButtonText: '取消', type: next === 'off_shelf' ? 'warning' : 'info' });
  } catch { return; }
  router.patch(`/tenant/goods/${row.id}`, {
    name: row.name,
    category_id: row.category_id,
    status: next,
    sku: { base_price_cents: row.sku_base_price_cents, code: row.sku_code, attrs_json: null },
  }, { preserveScroll: true });
}

function formatYuan(cents: number): string { return (cents / 100).toFixed(2); }

const columns = [
  { key: 'name', label: '商品名称' },
  { key: 'category_name', label: '分类', width: 160 },
  { key: 'sku_base_price_cents', label: '价格(元)', width: 120,
    formatter: (r: GoodsRow) => formatYuan(r.sku_base_price_cents) },
  { key: 'sku_code', label: 'SKU 编码', width: 160 },
  { key: 'status', label: '状态', width: 100 },
  { key: 'created_at', label: '创建时间', width: 200,
    formatter: (r: GoodsRow) => (r.created_at ? r.created_at.substring(0, 19).replace('T', ' ') : '') },
];
</script>

<template>
  <Head title="商品管理" />
  <PageHeader title="商品管理">
    <template #actions>
      <ElButton type="primary" :disabled="!props.has_categories" @click="goCreate">+ 新建商品</ElButton>
    </template>
  </PageHeader>

  <div class="bg-white rounded-md p-3 mb-3 flex gap-2 items-center shadow-[var(--shadow-card)]">
    <ElInput v-model="keyword" placeholder="商品名称" style="width: 240px" @keyup.enter="onSearch" />
    <ElSelect v-model="statusFilter" style="width: 140px">
      <ElOption label="全部状态" value="all" />
      <ElOption label="在售" value="active" />
      <ElOption label="下架" value="off_shelf" />
    </ElSelect>
    <ElButton @click="onSearch">筛选</ElButton>
    <ElButton @click="reset">重置</ElButton>
  </div>

  <DataTable
    :rows="props.rows"
    :total="props.total"
    :page="props.page"
    :page-size="props.pageSize"
    :columns="columns"
    @update:page="onPage"
    @update:pageSize="onPageSize"
  >
    <template #col-status="{ row }">
      <ElTag v-if="(row as GoodsRow).status === 'active'" type="success" size="small">在售</ElTag>
      <ElTag v-else type="info" size="small">下架</ElTag>
    </template>
    <template #actions="{ row }">
      <ElButton link size="small" @click="goEdit(row as GoodsRow)">编辑</ElButton>
      <ElButton link size="small" :type="(row as GoodsRow).status === 'active' ? 'warning' : 'primary'"
                @click="toggleStatus(row as GoodsRow)">
        {{ (row as GoodsRow).status === 'active' ? '下架' : '上架' }}
      </ElButton>
    </template>
    <template #empty>
      <div class="py-12 flex flex-col items-center gap-3 text-[13px]" style="color: var(--text-faint)">
        <span v-if="!props.has_categories">请先到「商品分类」页创建分类</span>
        <span v-else>暂无商品</span>
        <ElButton v-if="props.has_categories" type="primary" size="small" @click="goCreate">+ 新建第一个商品</ElButton>
        <ElButton v-else size="small" @click="goCategories">去创建分类</ElButton>
      </div>
    </template>
  </DataTable>
</template>
