<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import {
  ElForm, ElFormItem, ElInput, ElSelect, ElOption, ElButton,
  ElInputNumber, ElCard, ElMessage,
} from 'element-plus';
import TenantLayout from '@/layouts/TenantLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { enumDict, ownerTypeLabel } from '@/lib/itemTypes';

const itemTypeScopes = enumDict('item_type_scope');

defineOptions({ layout: TenantLayout });

interface ParentOption {
  id: string;
  parent_id: string | null;
  owner_type: 'TENANT' | 'STORE';
  owner_store_id: string | null;
  name: string;
  level: number;
  path: string;
}

defineProps<{ parents: ParentOption[] }>();

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
});
const processing = ref(false);

function submit() {
  processing.value = true;
  router.post('/tenant/categories', {
    ...form.value,
    owner_store_id: form.value.owner_type === 'STORE' ? (form.value.owner_store_id || null) : null,
    parent_id: form.value.parent_id || null,
    code: form.value.code || null,
  }, {
    onFinish: () => { processing.value = false; },
    onSuccess: () => ElMessage.success('已创建'),
  });
}
</script>

<template>
  <Head title="新建分类" />
  <PageHeader :breadcrumb="[{ label: '商品分类', to: '/tenant/categories' }, { label: '新建' }]">
    <template #actions>
      <ElButton @click="router.visit('/tenant/categories')">取消</ElButton>
      <ElButton type="primary" :loading="processing" @click="submit">创建</ElButton>
    </template>
  </PageHeader>

  <ElCard shadow="never" class="mt-3 max-w-2xl">
    <ElForm :model="form" label-width="100px">
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
          <ElOption v-for="(label, code) in itemTypeScopes" :key="code"
            :value="code" :label="label" />
        </ElSelect>
      </ElFormItem>
      <ElFormItem label="父分类">
        <ElSelect v-model="form.parent_id" clearable filterable style="width: 100%">
          <ElOption v-for="p in parents" :key="p.id"
            :value="p.id"
            :label="'│ '.repeat(p.level - 1) + p.name + ' (' + ownerTypeLabel(p.owner_type) + ')'" />
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
    </ElForm>
  </ElCard>
</template>
