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

interface Category {
  id: string;
  parent_id: string | null;
  owner_type: 'TENANT' | 'STORE';
  owner_store_id: string | null;
  category_type: 'BUSINESS' | 'INVENTORY' | 'BOTH';
  item_type_scope: string;
  name: string;
  code: string | null;
  sort_no: number;
  status: 'active' | 'disabled';
}

interface ParentOption {
  id: string;
  name: string;
  owner_type: 'TENANT' | 'STORE';
  level: number;
  path: string;
}

const props = defineProps<{ category: Category; parents: ParentOption[] }>();

const form = ref({
  owner_type: props.category.owner_type,
  owner_store_id: props.category.owner_store_id ?? '',
  category_type: props.category.category_type,
  item_type_scope: props.category.item_type_scope,
  parent_id: props.category.parent_id ?? '',
  name: props.category.name,
  code: props.category.code ?? '',
  sort_no: props.category.sort_no,
  status: props.category.status,
});
const processing = ref(false);

function submit() {
  processing.value = true;
  router.patch(`/tenant/categories/${props.category.id}`, {
    ...form.value,
    owner_store_id: form.value.owner_type === 'STORE' ? (form.value.owner_store_id || null) : null,
    parent_id: form.value.parent_id || null,
    code: form.value.code || null,
  }, {
    onFinish: () => { processing.value = false; },
    onSuccess: () => ElMessage.success('已更新'),
  });
}
</script>

<template>
  <Head title="编辑分类" />
  <PageHeader :breadcrumb="[{ label: '商品分类', to: '/tenant/categories' }, { label: props.category.name }]">
    <template #actions>
      <ElButton @click="router.visit('/tenant/categories')">取消</ElButton>
      <ElButton type="primary" :loading="processing" @click="submit">保存</ElButton>
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
        <ElInput v-model="form.code" maxlength="64" />
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
