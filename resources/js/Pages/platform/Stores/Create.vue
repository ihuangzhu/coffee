<script setup lang="ts">
/**
 * 新建门店：仅门店名称，status 后端强制 active。
 */
import { Head, router, useForm } from '@inertiajs/vue3';
import PlatformLayout from '@/layouts/PlatformLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { ElButton, ElForm, ElFormItem, ElInput } from 'element-plus';

defineOptions({ layout: PlatformLayout });

const props = defineProps<{ tenant: { id: string; name: string } }>();

const form = useForm({ name: '' });

function submit() { form.post(`/platform/tenants/${props.tenant.id}/stores`); }
function cancel() { router.visit(`/platform/tenants/${props.tenant.id}/stores`); }
</script>

<template>
  <Head :title="`新建门店 · ${props.tenant.name}`" />
  <PageHeader :title="`新建门店 · ${props.tenant.name}`" />
  <div class="bg-white rounded-md p-4 shadow-[var(--shadow-card)] max-w-[640px]">
    <ElForm label-width="120px" @submit.prevent="submit">
      <ElFormItem label="门店名称" :error="form.errors.name">
        <ElInput v-model="form.name" maxlength="100" show-word-limit style="width: 360px" />
      </ElFormItem>
      <ElFormItem>
        <ElButton type="primary" :loading="form.processing" native-type="submit">创建</ElButton>
        <ElButton @click="cancel">取消</ElButton>
      </ElFormItem>
    </ElForm>
  </div>
</template>
