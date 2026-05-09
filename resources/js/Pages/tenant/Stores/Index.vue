<script setup lang="ts">
/**
 * 租户后台 - 门店列表（只读）。
 * - 仅展示当前租户名下门店；新增/编辑/启停归平台后台。
 * - 行内"成员"按钮跳 /tenant/stores/{id}/users。
 * - disabled 门店仍出现在列表中（与 StoreSwitcher 仅取 active 不同），便于
 *   清理历史成员；但行内仍可进入成员管理。
 */
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { ElButton, ElInput, ElOption, ElSelect, ElTag } from 'element-plus';
import TenantLayout from '@/layouts/TenantLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DataTable from '@/components/DataTable.vue';

defineOptions({ layout: TenantLayout });

interface Row extends Record<string, unknown> {
  id: string;
  name: string;
  status: 'active' | 'disabled';
  created_at: string | null;
}

const page = usePage();
const props = computed(() => page.props as unknown as {
  rows: Row[];
  total: number;
  page: number;
  pageSize: number;
  q: string;
  status: 'all' | 'active' | 'disabled';
});

const keyword = ref(props.value.q);
const statusFilter = ref<'all' | 'active' | 'disabled'>(props.value.status);

/** 拉取列表，保留搜索/分页参数；空白关键词不参与过滤交由后端 trim 判定 */
function reload(params: Record<string, unknown> = {}) {
  router.get('/tenant/stores', {
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

function goMembers(row: Row) {
  router.visit(`/tenant/stores/${row.id}/users`);
}

const columns = [
  { key: 'name', label: '门店名称' },
  { key: 'status', label: '状态', width: 100 },
  {
    key: 'created_at', label: '创建时间', width: 200,
    formatter: (r: Row) => (r.created_at ? r.created_at.substring(0, 19).replace('T', ' ') : ''),
  },
];
</script>

<template>
  <Head title="门店" />
  <PageHeader title="门店">
    <template #actions>
      <span class="text-[12px]" :style="{ color: 'var(--text-faint)' }">
        新增 / 编辑门店请到平台后台
      </span>
    </template>
  </PageHeader>

  <div class="bg-white rounded-md p-3 mb-3 flex gap-2 items-center shadow-[var(--shadow-card)]">
    <ElInput v-model="keyword" placeholder="门店名称" style="width: 240px" @keyup.enter="onSearch" />
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
      <ElTag :type="(row as Row).status === 'active' ? 'success' : 'info'" size="small">
        {{ (row as Row).status === 'active' ? '启用' : '已禁用' }}
      </ElTag>
    </template>
    <template #actions="{ row }">
      <ElButton link size="small" type="primary" @click="goMembers(row as Row)">成员</ElButton>
    </template>
    <template #empty>
      <div class="py-12 flex flex-col items-center gap-3 text-[13px]" :style="{ color: 'var(--text-faint)' }">
        <span>本租户暂无门店，请到平台后台添加。</span>
      </div>
    </template>
  </DataTable>
</template>
