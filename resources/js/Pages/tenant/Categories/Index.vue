<script setup lang="ts">
import { ref, computed } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import { ElTree, ElButton, ElTag, ElMessage, ElMessageBox, ElSelect, ElOption } from 'element-plus'

interface Row {
  id: string
  parent_id: string | null
  owner_type: 'TENANT' | 'STORE'
  owner_store_id: string | null
  category_type: 'BUSINESS' | 'INVENTORY' | 'BOTH'
  item_type_scope: string
  name: string
  code: string | null
  level: number
  path: string
  sort_no: number
  status: 'active' | 'disabled'
}

const props = defineProps<{ rows: Row[] }>()

const ownerFilter = ref<'ALL' | 'TENANT' | 'STORE'>('ALL')
const typeFilter = ref<'ALL' | 'BUSINESS' | 'INVENTORY' | 'BOTH'>('ALL')

const tree = computed(() => {
  const filtered = props.rows.filter(r =>
    (ownerFilter.value === 'ALL' || r.owner_type === ownerFilter.value)
    && (typeFilter.value === 'ALL' || r.category_type === typeFilter.value)
  )
  const byId = new Map<string, Row & { children: any[] }>()
  filtered.forEach(r => byId.set(r.id, { ...r, children: [] }))
  const roots: any[] = []
  byId.forEach(node => {
    if (node.parent_id && byId.has(node.parent_id)) {
      byId.get(node.parent_id)!.children.push(node)
    } else {
      roots.push(node)
    }
  })
  return roots
})

const onDelete = async (id: string, name: string) => {
  await ElMessageBox.confirm(`确认删除分类「${name}」？`, '删除', { type: 'warning' })
  router.delete(`/tenant/categories/${id}`, {
    onSuccess: () => ElMessage.success('已删除'),
  })
}
</script>

<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-xl font-bold">分类管理</h1>
      <Link href="/tenant/categories/create">
        <ElButton type="primary">新建分类</ElButton>
      </Link>
    </div>

    <div class="flex gap-4 mb-4">
      <ElSelect v-model="ownerFilter" style="width: 160px">
        <ElOption label="全部所有者" value="ALL" />
        <ElOption label="租户公共" value="TENANT" />
        <ElOption label="门店私有" value="STORE" />
      </ElSelect>
      <ElSelect v-model="typeFilter" style="width: 160px">
        <ElOption label="全部类型" value="ALL" />
        <ElOption label="经营" value="BUSINESS" />
        <ElOption label="库存物料" value="INVENTORY" />
        <ElOption label="通用" value="BOTH" />
      </ElSelect>
    </div>

    <ElTree :data="tree" node-key="id" :default-expand-all="true"
            :props="{ label: 'name', children: 'children' }">
      <template #default="{ data }">
        <div class="flex items-center gap-2 w-full">
          <span class="font-medium">{{ data.name }}</span>
          <ElTag v-if="data.code" size="small">{{ data.code }}</ElTag>
          <ElTag size="small" :type="data.category_type === 'INVENTORY' ? 'warning' : 'success'">
            {{ data.category_type }}
          </ElTag>
          <ElTag size="small" :type="data.owner_type === 'STORE' ? 'info' : undefined">
            {{ data.owner_type }}
          </ElTag>
          <ElTag v-if="data.status === 'disabled'" size="small" type="danger">已停用</ElTag>
          <span class="text-xs text-gray-400">scope={{ data.item_type_scope }}</span>
          <span class="ml-auto flex gap-2">
            <Link :href="`/tenant/categories/${data.id}/edit`">
              <ElButton size="small">编辑</ElButton>
            </Link>
            <ElButton size="small" type="danger" @click="onDelete(data.id, data.name)">
              删除
            </ElButton>
          </span>
        </div>
      </template>
    </ElTree>
  </div>
</template>
