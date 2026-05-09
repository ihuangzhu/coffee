<script setup lang="ts">
/**
 * 总后台 - 租户列表。
 * 支持按名称模糊 + status 过滤；行级 actions：进入后台 / 编辑 / 启用·禁用切换。
 */
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { ElButton, ElInput, ElMessageBox, ElSelect, ElOption, ElTag, ElTooltip } from 'element-plus';
import PlatformLayout from '@/layouts/PlatformLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DataTable from '@/components/DataTable.vue';

defineOptions({ layout: PlatformLayout });

interface TenantRow extends Record<string, unknown> {
  id: string;
  name: string;
  status: 'active' | 'disabled';
  created_at: string | null;
}

const page = usePage();
const props = computed(() => page.props as unknown as {
  rows: TenantRow[];
  total: number;
  page: number;
  pageSize: number;
  q: string;
  status: 'all' | 'active' | 'disabled';
});

const keyword = ref(props.value.q);
const statusFilter = ref<'all' | 'active' | 'disabled'>(props.value.status);

function reload(params: Record<string, unknown> = {}) {
  router.get('/platform/tenants', {
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
function reset() { keyword.value = ''; statusFilter.value = 'all'; reload({ page: 1, q: '', status: 'all' }); }

function goCreate() { router.visit('/platform/tenants/create'); }
function goEdit(row: TenantRow) { router.visit(`/platform/tenants/${row.id}/edit`); }
function goStores(row: TenantRow) { router.visit(`/platform/tenants/${row.id}/stores`); }

async function enterTenant(row: TenantRow) {
  if (row.status !== 'active') return; // 兜底，按钮已禁用
  try {
    await ElMessageBox.confirm(`确认进入租户「${row.name}」后台？`, '进入租户后台',
      { confirmButtonText: '进入', cancelButtonText: '取消', type: 'info' });
  } catch { return; }
  router.post(`/platform/tenants/${row.id}/enter`);
}

async function toggleStatus(row: TenantRow) {
  const next = row.status === 'active' ? 'disabled' : 'active';
  const verb = next === 'disabled' ? '禁用' : '启用';
  try {
    await ElMessageBox.confirm(`确认${verb}租户「${row.name}」？`, `${verb}租户`,
      { confirmButtonText: verb, cancelButtonText: '取消', type: next === 'disabled' ? 'warning' : 'info' });
  } catch { return; }
  router.patch(`/platform/tenants/${row.id}`, { name: row.name, status: next }, { preserveScroll: true });
}

const columns = [
  { key: 'id', label: 'ID', width: 240 },
  { key: 'name', label: '租户名称' },
  { key: 'status', label: '状态', width: 100 },
  { key: 'created_at', label: '创建时间', width: 200,
    formatter: (r: TenantRow) => (r.created_at ? r.created_at.substring(0, 19).replace('T', ' ') : '') },
];
</script>

<template>
  <Head title="租户管理" />
  <PageHeader title="租户管理">
    <template #actions>
      <ElButton type="primary" @click="goCreate">+ 新建租户</ElButton>
    </template>
  </PageHeader>

  <div class="bg-white rounded-md p-3 mb-3 flex gap-2 items-center shadow-[var(--shadow-card)]">
    <ElInput v-model="keyword" placeholder="租户名称" style="width: 240px" @keyup.enter="onSearch" />
    <ElSelect v-model="statusFilter" style="width: 140px">
      <ElOption label="全部状态" value="all" />
      <ElOption label="启用" value="active" />
      <ElOption label="已禁用" value="disabled" />
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
      <ElTag :type="(row as TenantRow).status === 'active' ? 'success' : 'info'" size="small">
        {{ (row as TenantRow).status === 'active' ? '启用' : '已禁用' }}
      </ElTag>
    </template>
    <template #actions="{ row }">
      <ElTooltip v-if="(row as TenantRow).status !== 'active'" content="租户已禁用" placement="top">
        <span><ElButton link size="small" disabled>进入后台</ElButton></span>
      </ElTooltip>
      <ElButton v-else link size="small" type="primary" @click="enterTenant(row as TenantRow)">进入后台</ElButton>
      <ElButton link size="small" @click="goStores(row as TenantRow)">门店</ElButton>
      <ElButton link size="small" @click="goEdit(row as TenantRow)">编辑</ElButton>
      <ElButton link size="small"
        :type="(row as TenantRow).status === 'active' ? 'warning' : 'success'"
        @click="toggleStatus(row as TenantRow)">
        {{ (row as TenantRow).status === 'active' ? '禁用' : '启用' }}
      </ElButton>
    </template>
  </DataTable>
</template>
