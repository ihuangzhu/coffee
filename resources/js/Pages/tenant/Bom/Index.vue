<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { ElButton, ElTable, ElTableColumn, ElTabs, ElTabPane, ElPagination, ElTag, ElPopconfirm } from 'element-plus'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

interface BomRow {
  id: string
  output_sku: { id: string; sku_code: string; spec_name: string; item: { name: string } }
  output_qty: string
  bom_type: 'STANDARD' | 'STORE_CUSTOM'
  store_id: string | null
  status: 'active' | 'disabled'
  components: { id: string }[]
}

interface Props {
  boms: { data: BomRow[]; current_page: number; last_page: number; per_page: number; total: number }
  filterBomType?: 'STANDARD' | 'STORE_CUSTOM' | null
}

const props = defineProps<Props>()
const { t } = useI18n()
const activeTab = ref<string>(props.filterBomType ?? 'STANDARD')

function changeTab(tab: string) {
  router.get('/tenant/boms', { bom_type: tab }, { preserveState: false })
}

function destroy(id: string) {
  router.delete('/tenant/boms/' + id)
}

function pageTo(page: number) {
  router.get('/tenant/boms', { page, bom_type: activeTab.value }, { preserveState: false })
}
</script>

<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-2xl font-bold">{{ t('nav.inventory.boms') }}</h1>
      <Link href="/tenant/boms/create">
        <ElButton type="primary">+ 新建配方</ElButton>
      </Link>
    </div>

    <ElTabs :model-value="activeTab" @update:model-value="(v) => changeTab(String(v))">
      <ElTabPane label="租户公共 (STANDARD)" name="STANDARD" />
      <ElTabPane label="门店私有 (STORE_CUSTOM)" name="STORE_CUSTOM" />
    </ElTabs>

    <ElTable :data="boms.data" border>
      <ElTableColumn label="产出 SKU" min-width="200">
        <template #default="{ row }">
          {{ row.output_sku.item.name }} / {{ row.output_sku.spec_name }}
        </template>
      </ElTableColumn>
      <ElTableColumn label="产出量" prop="output_qty" width="100" />
      <ElTableColumn label="组件数" width="100">
        <template #default="{ row }">{{ row.components.length }}</template>
      </ElTableColumn>
      <ElTableColumn label="状态" width="100">
        <template #default="{ row }">
          <ElTag :type="row.status === 'active' ? 'success' : 'info'">{{ row.status }}</ElTag>
        </template>
      </ElTableColumn>
      <ElTableColumn label="操作" width="200">
        <template #default="{ row }">
          <Link :href="'/tenant/boms/' + row.id + '/edit'">
            <ElButton size="small">编辑</ElButton>
          </Link>
          <ElPopconfirm title="确认删除？" @confirm="destroy(row.id)">
            <template #reference>
              <ElButton size="small" type="danger">删除</ElButton>
            </template>
          </ElPopconfirm>
        </template>
      </ElTableColumn>
    </ElTable>

    <div class="mt-4 flex justify-end">
      <ElPagination
        :current-page="boms.current_page"
        :page-count="boms.last_page"
        :page-size="boms.per_page"
        :total="boms.total"
        layout="prev, pager, next"
        @current-change="pageTo"
      />
    </div>
  </div>
</template>
