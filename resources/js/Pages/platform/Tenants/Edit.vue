<script setup lang="ts">
/**
 * 编辑租户：仅 name + status 两个字段。
 */
import { Head, router, useForm } from '@inertiajs/vue3';
import PlatformLayout from '@/layouts/PlatformLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { ElButton, ElForm, ElFormItem, ElInput, ElRadioGroup, ElRadio, ElCard } from 'element-plus';

defineOptions({ layout: PlatformLayout });

const props = defineProps<{ tenant: { id: string; name: string; status: 'active' | 'disabled' } }>();

const form = useForm({ name: props.tenant.name, status: props.tenant.status });

function submit() { form.patch(`/platform/tenants/${props.tenant.id}`); }
function cancel() { router.visit('/platform/tenants'); }
</script>

<template>
  <Head :title="`编辑租户 · ${props.tenant.name}`" />
  <PageHeader :breadcrumb="[{ label: '租户管理', to: '/platform/tenants' }, { label: props.tenant.name }]">
    <template #actions>
      <ElButton @click="cancel">取消</ElButton>
      <ElButton type="primary" :loading="form.processing" @click="submit">保存</ElButton>
    </template>
  </PageHeader>
  <ElCard shadow="never" class="mt-3 max-w-[640px]">
    <ElForm label-width="120px">
      <ElFormItem label="租户名称" :error="form.errors.name">
        <ElInput v-model="form.name" maxlength="100" show-word-limit style="width: 360px" />
      </ElFormItem>
      <ElFormItem label="状态" :error="form.errors.status">
        <ElRadioGroup v-model="form.status">
          <ElRadio value="active">启用</ElRadio>
          <ElRadio value="disabled">禁用</ElRadio>
        </ElRadioGroup>
        <div class="text-[12px] mt-1" style="color: var(--text-muted)">禁用后该租户下所有成员将无法登录。</div>
      </ElFormItem>
    </ElForm>
  </ElCard>
</template>
