<script setup lang="ts">
/**
 * 新建门店：仅门店名称，status 后端强制 active。
 */
import { Head, router, useForm } from '@inertiajs/vue3';
import PlatformLayout from '@/layouts/PlatformLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { ElButton, ElForm, ElFormItem, ElInput, ElCard } from 'element-plus';

defineOptions({ layout: PlatformLayout });

const props = defineProps<{ tenant: { id: string; name: string } }>();

const form = useForm({ name: '' });

function submit() { form.post(`/platform/tenants/${props.tenant.id}/stores`); }
function cancel() { router.visit(`/platform/tenants/${props.tenant.id}/stores`); }
</script>

<template>
  <Head :title="`新建门店 · ${props.tenant.name}`" />
  <PageHeader :breadcrumb="[
    { label: '租户管理', to: '/platform/tenants' },
    { label: `${props.tenant.name} 门店`, to: `/platform/tenants/${props.tenant.id}/stores` },
    { label: '新建门店' },
  ]">
    <template #actions>
      <ElButton @click="cancel">取消</ElButton>
      <ElButton type="primary" :loading="form.processing" @click="submit">创建</ElButton>
    </template>
  </PageHeader>
  <ElCard shadow="never" class="mt-3 max-w-[640px]">
    <ElForm label-width="120px">
      <ElFormItem label="门店名称" :error="form.errors.name">
        <ElInput v-model="form.name" maxlength="100" show-word-limit style="width: 360px" />
      </ElFormItem>
    </ElForm>
  </ElCard>
</template>
