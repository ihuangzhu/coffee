<script setup lang="ts">
import { ref } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import { ElForm, ElFormItem, ElInput, ElSelect, ElOption, ElButton, ElInputNumber, ElMessage } from 'element-plus'

interface ParentOption {
  id: string
  parent_id: string | null
  owner_type: 'TENANT' | 'STORE'
  owner_store_id: string | null
  name: string
  level: number
  path: string
}

defineProps<{ parents: ParentOption[] }>()

const form = ref({
  owner_type: 'TENANT' as 'TENANT' | 'STORE',
  owner_store_id: '' as string,
  category_type: 'BUSINESS' as 'BUSINESS' | 'INVENTORY' | 'BOTH',
  item_type_scope: 'ALL' as string,
  parent_id: '' as string,
  name: '',
  code: '',
  sort_no: 0,
  status: 'active' as 'active' | 'disabled',
})

const submit = () => {
  router.post('/tenant/categories', {
    ...form.value,
    owner_store_id: form.value.owner_type === 'STORE'
      ? (form.value.owner_store_id || null) : null,
    parent_id: form.value.parent_id || null,
    code: form.value.code || null,
  }, {
    onSuccess: () => ElMessage.success('已创建'),
  })
}
</script>

<template>
  <div class="p-6 max-w-2xl">
    <h1 class="text-xl font-bold mb-4">新建分类</h1>

    <ElForm :model="form" label-width="120px" @submit.prevent="submit">
      <ElFormItem label="所有者">
        <ElSelect v-model="form.owner_type">
          <ElOption label="租户公共" value="TENANT" />
          <ElOption label="门店私有" value="STORE" />
        </ElSelect>
      </ElFormItem>
      <ElFormItem v-if="form.owner_type === 'STORE'" label="门店 ID">
        <ElInput v-model="form.owner_store_id" placeholder="门店 ULID" />
      </ElFormItem>
      <ElFormItem label="分类类型">
        <ElSelect v-model="form.category_type">
          <ElOption label="经营 (BUSINESS)" value="BUSINESS" />
          <ElOption label="库存物料 (INVENTORY)" value="INVENTORY" />
          <ElOption label="通用 (BOTH)" value="BOTH" />
        </ElSelect>
      </ElFormItem>
      <ElFormItem label="挂载范围">
        <ElSelect v-model="form.item_type_scope">
          <ElOption label="全部 (ALL)" value="ALL" />
          <ElOption label="可销售商品" value="SALE_PRODUCT" />
          <ElOption label="原料" value="RAW_MATERIAL" />
          <ElOption label="半成品" value="SEMI_FINISHED" />
          <ElOption label="成品" value="FINISHED_GOOD" />
          <ElOption label="服务" value="SERVICE" />
          <ElOption label="包材" value="PACKAGE" />
        </ElSelect>
      </ElFormItem>
      <ElFormItem label="父分类">
        <ElSelect v-model="form.parent_id" clearable filterable>
          <ElOption v-for="p in parents" :key="p.id"
            :value="p.id"
            :label="'│ '.repeat(p.level - 1) + p.name + ' (' + p.owner_type + ')'" />
        </ElSelect>
      </ElFormItem>
      <ElFormItem label="名称" required>
        <ElInput v-model="form.name" maxlength="100" />
      </ElFormItem>
      <ElFormItem label="编码">
        <ElInput v-model="form.code" placeholder="如 B-DRINK 或 I-RAW-MILK" maxlength="64" />
      </ElFormItem>
      <ElFormItem label="排序">
        <ElInputNumber v-model="form.sort_no" :min="0" :max="9999" />
      </ElFormItem>
      <ElFormItem label="状态">
        <ElSelect v-model="form.status">
          <ElOption label="启用" value="active" />
          <ElOption label="停用" value="disabled" />
        </ElSelect>
      </ElFormItem>
      <ElFormItem>
        <ElButton type="primary" native-type="submit">创建</ElButton>
        <Link href="/tenant/categories" class="ml-2">
          <ElButton>取消</ElButton>
        </Link>
      </ElFormItem>
    </ElForm>
  </div>
</template>
