<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import TenantLayout from '@/layouts/TenantLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DataTable from '@/components/DataTable.vue';
import { ElButton, ElInput, ElMessage, ElMessageBox } from 'element-plus';

defineOptions({ layout: TenantLayout });

interface UserRow extends Record<string, unknown> {
  id: string;
  name: string;
  phone: string;
  tenant_role_name: string | null;
  created_at: string;
}

const page = usePage();
const props = computed(() => page.props as unknown as {
  rows: UserRow[];
  total: number;
  page: number;
  pageSize: number;
  q: string;
});

const keyword = ref(props.value.q);

function reload(params: Record<string, unknown> = {}) {
  router.get(
    '/tenant/users',
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

function goCreate() { router.visit('/tenant/users/create'); }
function goEdit(row: UserRow) { router.visit(`/tenant/users/${row.id}/edit`); }
function destroy(row: UserRow) {
  ElMessageBox.confirm(`确认删除用户 ${row.name}？`, '提示', { type: 'warning' })
    .then(() => router.delete(`/tenant/users/${row.id}`))
    .catch(() => { /* 取消 */ });
}

/**
 * 重置指定员工的登录密码：弹窗输入新密码（min:8），后端再次校验长度与权限。
 */
function resetPassword(row: UserRow) {
  ElMessageBox.prompt(`为 ${row.name} 设置新密码（至少 8 位）`, '重置密码', {
    inputType: 'password',
    inputValidator: (v: string) => (v && v.length >= 8) || '密码至少 8 位',
    confirmButtonText: '重置',
    cancelButtonText: '取消',
  }).then(({ value }) => {
    router.post(`/tenant/users/${row.id}/reset-password`, { password: value }, {
      preserveScroll: true,
      onSuccess: () => ElMessage.success('密码已重置'),
    });
  }).catch(() => { /* 取消 */ });
}

const columns = [
  { key: 'name', label: '姓名', width: 180 },
  { key: 'phone', label: '手机号' },
  { key: 'tenant_role_name', label: '租户级角色', width: 160, formatter: (r: UserRow) => r.tenant_role_name ?? '—' },
  {
    key: 'created_at',
    label: '创建时间',
    width: 200,
    formatter: (r: UserRow) => (r.created_at ? r.created_at.substring(0, 19).replace('T', ' ') : ''),
  },
];
</script>

<template>
  <Head title="用户管理" />
  <PageHeader title="用户管理">
    <template #actions>
      <ElButton type="primary" @click="goCreate">+ 新增用户</ElButton>
    </template>
  </PageHeader>

  <div class="bg-white rounded-md p-3 mb-3 flex gap-2 items-center shadow-[var(--shadow-card)]">
    <ElInput v-model="keyword" placeholder="姓名或手机号" style="width: 240px" @keyup.enter="onSearch" />
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
    <template #actions="{ row }">
      <ElButton link size="small" @click="goEdit(row as UserRow)">编辑</ElButton>
      <ElButton link size="small" @click="resetPassword(row as UserRow)">重置密码</ElButton>
      <ElButton link size="small" type="danger" @click="destroy(row as UserRow)">删除</ElButton>
    </template>
  </DataTable>
</template>
