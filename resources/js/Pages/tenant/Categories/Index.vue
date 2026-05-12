<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { ElTree, ElButton, ElTag, ElMessage, ElMessageBox, ElSelect, ElOption, ElCard } from 'element-plus';
import TenantLayout from '@/layouts/TenantLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { categoryTypeLabel, ownerTypeLabel, itemTypeScopeLabel } from '@/lib/itemTypes';

defineOptions({ layout: TenantLayout });

interface Row {
  id: string;
  parent_id: string | null;
  owner_type: 'TENANT' | 'STORE';
  owner_store_id: string | null;
  category_type: 'BUSINESS' | 'INVENTORY' | 'BOTH';
  item_type_scope: string;
  name: string;
  code: string | null;
  level: number;
  path: string;
  sort_no: number;
  status: 'active' | 'disabled';
}

interface TreeNode extends Row { children: TreeNode[] }

const props = defineProps<{ rows: Row[] }>();

const ownerFilter = ref<'ALL' | 'TENANT' | 'STORE'>('ALL');
const typeFilter = ref<'ALL' | 'BUSINESS' | 'INVENTORY' | 'BOTH'>('ALL');

const tree = computed<TreeNode[]>(() => {
  const filtered = props.rows.filter((r) =>
    (ownerFilter.value === 'ALL' || r.owner_type === ownerFilter.value)
    && (typeFilter.value === 'ALL' || r.category_type === typeFilter.value),
  );
  const byId = new Map<string, TreeNode>();
  filtered.forEach((r) => byId.set(r.id, { ...r, children: [] }));
  const roots: TreeNode[] = [];
  byId.forEach((node) => {
    if (node.parent_id && byId.has(node.parent_id)) byId.get(node.parent_id)!.children.push(node);
    else roots.push(node);
  });
  return roots;
});

function goCreate() { router.visit('/tenant/categories/create'); }
function goEdit(id: string) { router.visit(`/tenant/categories/${id}/edit`); }

async function destroy(id: string, name: string) {
  try {
    await ElMessageBox.confirm(`确认删除分类「${name}」？`, '删除', { type: 'warning' });
  } catch { return; }
  router.delete(`/tenant/categories/${id}`, {
    onSuccess: () => ElMessage.success('已删除'),
  });
}
</script>

<template>
  <Head title="商品分类" />
  <PageHeader>
    <template #filter>
      <ElSelect v-model="ownerFilter" style="width: 140px">
        <ElOption label="全部所有者" value="ALL" />
        <ElOption label="租户公共" value="TENANT" />
        <ElOption label="门店私有" value="STORE" />
      </ElSelect>
      <ElSelect v-model="typeFilter" style="width: 140px">
        <ElOption label="全部类型" value="ALL" />
        <ElOption label="经营" value="BUSINESS" />
        <ElOption label="库存物料" value="INVENTORY" />
        <ElOption label="通用" value="BOTH" />
      </ElSelect>
    </template>
    <template #actions>
      <ElButton type="primary" @click="goCreate">+ 新建分类</ElButton>
    </template>
  </PageHeader>

  <ElCard shadow="never" class="mt-3">
    <ElTree :data="tree" node-key="id" :default-expand-all="true"
            :props="{ label: 'name', children: 'children' }">
      <template #default="{ data }: { data: TreeNode }">
        <div class="flex items-center gap-2 w-full">
          <span class="font-medium">{{ data.name }}</span>
          <ElTag v-if="data.code" size="small">{{ data.code }}</ElTag>
          <ElTag size="small" :type="data.category_type === 'INVENTORY' ? 'warning' : 'success'">
            {{ categoryTypeLabel(data.category_type) }}
          </ElTag>
          <ElTag size="small" :type="data.owner_type === 'STORE' ? 'info' : undefined">
            {{ ownerTypeLabel(data.owner_type) }}
          </ElTag>
          <ElTag v-if="data.status === 'disabled'" size="small" type="danger">已停用</ElTag>
          <span class="text-[12px]" style="color: var(--text-faint)">范围：{{ itemTypeScopeLabel(data.item_type_scope) }}</span>
          <span class="ml-auto flex gap-1">
            <ElButton link size="small" @click.stop="goEdit(data.id)">编辑</ElButton>
            <ElButton link size="small" type="danger" @click.stop="destroy(data.id, data.name)">删除</ElButton>
          </span>
        </div>
      </template>
    </ElTree>
  </ElCard>
</template>
