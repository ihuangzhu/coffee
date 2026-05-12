<script setup lang="ts">
/**
 * 总后台 - 指定租户下门店列表。
 * 支持名称模糊 + status 过滤；行级 actions：编辑 / 启用·禁用切换。
 */
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { ElButton, ElInput, ElMessageBox, ElSelect, ElOption, ElTag } from 'element-plus';
import PlatformLayout from '@/layouts/PlatformLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DataTable from '@/components/DataTable.vue';

defineOptions({ layout: PlatformLayout });

interface StoreRow extends Record<string, unknown> {
  id: string;
  name: string;
  status: 'active' | 'disabled';
  created_at: string | null;
}

const page = usePage();
const props = computed(() => page.props as unknown as {
  tenant: { id: string; name: string };
  rows: StoreRow[];
  total: number;
  page: number;
  pageSize: number;
  q: string;
  status: 'all' | 'active' | 'disabled';
});

const keyword = ref(props.value.q);
const statusFilter = ref<'all' | 'active' | 'disabled'>(props.value.status);

function reload(params: Record<string, unknown> = {}) {
  router.get(`/platform/tenants/${props.value.tenant.id}/stores`, {
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

function goCreate() { router.visit(`/platform/tenants/${props.value.tenant.id}/stores/create`); }
function goEdit(row: StoreRow) { router.visit(`/platform/tenants/${props.value.tenant.id}/stores/${row.id}/edit`); }

async function toggleStatus(row: StoreRow) {
  const next = row.status === 'active' ? 'disabled' : 'active';
  const verb = next === 'disabled' ? '禁用' : '启用';
  try {
    await ElMessageBox.confirm(`确认${verb}门店「${row.name}」？`, `${verb}门店`,
      { confirmButtonText: verb, cancelButtonText: '取消', type: next === 'disabled' ? 'warning' : 'info' });
  } catch { return; }
  router.patch(`/platform/tenants/${props.value.tenant.id}/stores/${row.id}`,
    { name: row.name, status: next },
    { preserveScroll: true });
}

const columns = [
  { key: 'id', label: 'ID', width: 240 },
  { key: 'name', label: '门店名称' },
  { key: 'status', label: '状态', width: 100 },
  { key: 'created_at', label: '创建时间', width: 200,
    formatter: (r: StoreRow) => (r.created_at ? r.created_at.substring(0, 19).replace('T', ' ') : '') },
];
</script>

<template>
  <Head :title="`${props.tenant.name} · 门店管理`" />
  <PageHeader :breadcrumb="[
    { label: '租户管理', to: '/platform/tenants' },
    { label: `${props.tenant.name} 门店` },
  ]">
    <template #filter>
      <ElInput v-model="keyword" placeholder="门店名称" style="width: 240px" @keyup.enter="onSearch" />
      <ElSelect v-model="statusFilter" style="width: 140px">
        <ElOption label="全部状态" value="all" />
        <ElOption label="启用" value="active" />
        <ElOption label="已禁用" value="disabled" />
      </ElSelect>
      <ElButton type="primary" @click="onSearch">筛选</ElButton>
      <ElButton @click="reset">重置</ElButton>
    </template>
    <template #actions>
      <ElButton type="primary" @click="goCreate">+ 新建门店</ElButton>
    </template>
  </PageHeader>

  <div class="mt-3">
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
      <ElTag :type="(row as StoreRow).status === 'active' ? 'success' : 'info'" size="small">
        {{ (row as StoreRow).status === 'active' ? '启用' : '已禁用' }}
      </ElTag>
    </template>
    <template #actions="{ row }">
      <ElButton link size="small" @click="goEdit(row as StoreRow)">编辑</ElButton>
      <ElButton link size="small"
        :type="(row as StoreRow).status === 'active' ? 'warning' : 'success'"
        @click="toggleStatus(row as StoreRow)">
        {{ (row as StoreRow).status === 'active' ? '禁用' : '启用' }}
      </ElButton>
    </template>
    <template #empty>
      <div class="py-12 flex flex-col items-center gap-3 text-[13px]" style="color: var(--text-faint)">
        <span>该租户暂无门店</span>
        <ElButton type="primary" size="small" @click="goCreate">+ 新建第一家门店</ElButton>
      </div>
    </template>
  </DataTable>
  </div>
</template>
