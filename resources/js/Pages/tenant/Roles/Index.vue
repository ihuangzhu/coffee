<script setup lang="ts">
/**
 * 租户后台角色管理列表页。
 * 数据由 RolesController::index 注入；列表项支持编辑/删除（仅自建角色），
 * 系统/全局角色显示"内置"徽标，操作列灰显。
 */
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import TenantLayout from '@/layouts/TenantLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DataTable from '@/components/DataTable.vue';
import { ElButton, ElMessageBox, ElTag } from 'element-plus';

defineOptions({ layout: TenantLayout });

interface RoleRow extends Record<string, unknown> {
  id: string;
  tenant_id: string | null;
  name: string;
  scope: string;
  permissions: string[];
  is_system: boolean;
  in_use_count: number;
}

const page = usePage();
const props = computed(() => page.props as unknown as { roles: RoleRow[] });

function goCreate() { router.visit('/tenant/roles/create'); }
function goEdit(row: RoleRow) { router.visit(`/tenant/roles/${row.id}/edit`); }

async function destroy(row: RoleRow) {
  try {
    await ElMessageBox.confirm(`确认删除角色「${row.name}」？`, '提示', { type: 'warning' });
    router.delete(`/tenant/roles/${row.id}`);
  } catch {
    // 用户取消
  }
}

const columns = [
  { key: 'name', label: '名称', minWidth: 220 },
  { key: 'scope', label: '作用域', width: 120, formatter: (r: RoleRow) => (r.scope === 'tenant' ? '租户级' : '门店级') },
  { key: 'permissions', label: '权限数', width: 100, formatter: (r: RoleRow) => String(r.permissions.length) },
  { key: 'in_use_count', label: '在用人数', width: 120 },
];

// 仅本租户自建（tenant_id 非 null 且非系统）可改可删
function canManage(row: RoleRow): boolean {
  return !row.is_system && row.tenant_id !== null;
}
</script>

<template>
  <Head title="角色管理" />
  <PageHeader title="角色管理">
    <template #actions>
      <ElButton type="primary" @click="goCreate">+ 新建角色</ElButton>
    </template>
  </PageHeader>

  <DataTable
    :rows="props.roles"
    :total="props.roles.length"
    :page="1"
    :page-size="props.roles.length || 1"
    :columns="columns"
  >
    <template #col-name="{ row }">
      <span class="font-medium">{{ (row as RoleRow).name }}</span>
      <ElTag v-if="(row as RoleRow).is_system" size="small" type="info" class="ml-2">内置</ElTag>
    </template>
    <template #actions="{ row }">
      <template v-if="canManage(row as RoleRow)">
        <ElButton link size="small" @click="goEdit(row as RoleRow)">编辑</ElButton>
        <ElButton link size="small" type="danger" @click="destroy(row as RoleRow)">删除</ElButton>
      </template>
      <span v-else class="text-[12px]" style="color: var(--text-faint)">—</span>
    </template>
  </DataTable>
</template>
