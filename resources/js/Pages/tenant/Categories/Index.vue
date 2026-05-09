<script setup lang="ts">
/**
 * 租户后台 - 商品分类列表（平列）。
 * 极简：不做搜索/状态过滤；行级 actions：编辑 / 删除。
 */
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { ElButton, ElMessageBox, ElMessage } from 'element-plus';
import TenantLayout from '@/layouts/TenantLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DataTable from '@/components/DataTable.vue';

defineOptions({ layout: TenantLayout });

interface CategoryRow extends Record<string, unknown> {
  id: string;
  name: string;
  sort: number;
}

const page = usePage();
const props = computed(() => page.props as unknown as {
  rows: CategoryRow[];
  total: number;
  page: number;
  pageSize: number;
});

function reload(params: Record<string, unknown> = {}) {
  router.get('/tenant/categories', {
    page: props.value.page,
    per_page: props.value.pageSize,
    ...params,
  }, { preserveState: true, preserveScroll: true });
}

function onPage(n: number) { reload({ page: n }); }
function onPageSize(n: number) { reload({ page: 1, per_page: n }); }
function goCreate() { router.visit('/tenant/categories/create'); }
function goEdit(row: CategoryRow) { router.visit(`/tenant/categories/${row.id}/edit`); }

async function destroy(row: CategoryRow) {
  try {
    await ElMessageBox.confirm(`确认删除分类「${row.name}」？`, '删除分类', {
      confirmButtonText: '删除', cancelButtonText: '取消', type: 'warning',
    });
  } catch { return; }
  router.delete(`/tenant/categories/${row.id}`, {
    preserveScroll: true,
    onError: (errs) => { if (errs.category) ElMessage.error(errs.category as string); },
  });
}

const columns = [
  { key: 'name', label: '分类名称' },
  { key: 'sort', label: '排序', width: 100 },
];
</script>

<template>
  <Head title="商品分类" />
  <PageHeader title="商品分类">
    <template #actions>
      <ElButton type="primary" @click="goCreate">+ 新建分类</ElButton>
    </template>
  </PageHeader>

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
      <ElButton link size="small" @click="goEdit(row as CategoryRow)">编辑</ElButton>
      <ElButton link size="small" type="danger" @click="destroy(row as CategoryRow)">删除</ElButton>
    </template>
    <template #empty>
      <div class="py-12 flex flex-col items-center gap-3 text-[13px]" style="color: var(--text-faint)">
        <span>暂无分类</span>
        <ElButton type="primary" size="small" @click="goCreate">+ 新建第一个分类</ElButton>
      </div>
    </template>
  </DataTable>
</template>
