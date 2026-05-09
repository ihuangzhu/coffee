<script setup lang="ts">
/**
 * 编辑门店：可改 name 与 status。
 * 禁用语义见底部说明：不强制踢出已选会话，下次请求自动剔除。
 */
import { Head, router, useForm } from '@inertiajs/vue3';
import PlatformLayout from '@/layouts/PlatformLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { ElButton, ElForm, ElFormItem, ElInput, ElRadioGroup, ElRadio } from 'element-plus';

defineOptions({ layout: PlatformLayout });

const props = defineProps<{
  tenant: { id: string; name: string };
  store: { id: string; name: string; status: 'active' | 'disabled' };
}>();

const form = useForm({ name: props.store.name, status: props.store.status });

function submit() { form.patch(`/platform/tenants/${props.tenant.id}/stores/${props.store.id}`); }
function cancel() { router.visit(`/platform/tenants/${props.tenant.id}/stores`); }
</script>

<template>
  <Head :title="`编辑门店 · ${props.store.name}`" />
  <PageHeader :title="`编辑门店 · ${props.store.name}`" />
  <div class="bg-white rounded-md p-4 shadow-[var(--shadow-card)] max-w-[640px]">
    <ElForm label-width="120px" @submit.prevent="submit">
      <ElFormItem label="门店名称" :error="form.errors.name">
        <ElInput v-model="form.name" maxlength="100" show-word-limit style="width: 360px" />
      </ElFormItem>
      <ElFormItem label="状态" :error="form.errors.status">
        <ElRadioGroup v-model="form.status">
          <ElRadio value="active">启用</ElRadio>
          <ElRadio value="disabled">禁用</ElRadio>
        </ElRadioGroup>
        <div class="text-[12px] mt-1" style="color: var(--text-muted)">
          禁用后该门店不出现在租户切换器与默认推导中；已选中该门店的会话下次请求自动重选其它启用门店。
        </div>
      </ElFormItem>
      <ElFormItem>
        <ElButton type="primary" :loading="form.processing" native-type="submit">保存</ElButton>
        <ElButton @click="cancel">取消</ElButton>
      </ElFormItem>
    </ElForm>
  </div>
</template>
