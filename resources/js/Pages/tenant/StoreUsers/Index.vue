<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import TenantLayout from '@/layouts/TenantLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DataTable from '@/components/DataTable.vue';
import { ElButton, ElInput, ElMessageBox, ElTag } from 'element-plus';

defineOptions({ layout: TenantLayout });

interface Row extends Record<string, unknown> {
  user_id: string;
  name: string;
  phone: string;
  role_id: string;
  role_name: string;
  role_is_system: boolean;
  joined_at: string;
}

const page = usePage();
const props = computed(() => page.props as unknown as {
  store: { id: string; name: string };
  rows: Row[];
  total: number;
  page: number;
  pageSize: number;
  q: string;
});

const keyword = ref(props.value.q);

/**
 * 拉取列表：保留搜索/分页参数，未传值则继承当前页。
 */
function reload(params: Record<string, unknown> = {}) {
  router.get(
    `/tenant/stores/${props.value.store.id}/users`,
    {
      page: props.value.page,
      per_page: props.value.pageSize,
      q: keyword.value,
      ...params,
    },
    { preserveState: true, preserveScroll: true },
  );
}

function onPage(n: number) { reload({ page: n }); }
function onPageSize(n: number) { reload({ page: 1, per_page: n }); }
function onSearch() { reload({ page: 1 }); }
function reset() { keyword.value = ''; reload({ page: 1, q: '' }); }

function goCreate() { router.visit(`/tenant/stores/${props.value.store.id}/users/create`); }
function goEdit(r: Row) { router.visit(`/tenant/stores/${props.value.store.id}/users/${r.user_id}/edit`); }
function destroy(r: Row) {
  ElMessageBox.confirm(`确认将 ${r.name} 移出本门店？`, '提示', { type: 'warning' })
    .then(() => router.delete(`/tenant/stores/${props.value.store.id}/users/${r.user_id}`))
    .catch(() => { /* 取消 */ });
}

const columns = [
  { key: 'name', label: '姓名', width: 160 },
  { key: 'phone', label: '手机号' },
  // role_name 列由 col-role_name slot 自定义渲染，加系统角色标签
  { key: 'role_name', label: '门店角色', width: 200 },
  {
    key: 'joined_at', label: '加入时间', width: 200,
    formatter: (r: Row) => (r.joined_at ? r.joined_at.substring(0, 19).replace('T', ' ') : ''),
  },
];
</script>

<template>
  <Head :title="`${props.store.name} · 人员`" />
  <PageHeader :breadcrumb="[
    { label: '门店列表', to: '/tenant/stores' },
    { label: `${props.store.name} 成员` },
  ]">
    <template #filter>
      <ElInput v-model="keyword" placeholder="姓名或手机号" style="width: 240px" @keyup.enter="onSearch" />
      <ElButton type="primary" @click="onSearch">筛选</ElButton>
      <ElButton @click="reset">重置</ElButton>
    </template>
    <template #actions>
      <ElButton type="primary" @click="goCreate">+ 加入人员</ElButton>
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
    <template #col-role_name="{ row }">
      <span>{{ (row as Row).role_name }}</span>
      <ElTag v-if="(row as Row).role_is_system" size="small" type="info" class="ml-2">系统</ElTag>
    </template>
    <template #actions="{ row }">
      <ElButton link size="small" @click="goEdit(row as Row)">改角色</ElButton>
      <ElButton link size="small" type="danger" @click="destroy(row as Row)">移出</ElButton>
    </template>
    <template #empty>
      <div class="py-12 flex flex-col items-center gap-3 text-[13px]" style="color: var(--text-faint)">
        <span>本门店还没有任何成员</span>
        <ElButton type="primary" size="small" @click="goCreate">+ 加入第一位人员</ElButton>
      </div>
    </template>
  </DataTable>
  </div>
</template>
