<script setup lang="ts">
/**
 * 租户后台 - 新建商品分类。
 */
import { Head, router, useForm } from '@inertiajs/vue3';
import { ElButton, ElForm, ElFormItem, ElInput, ElInputNumber } from 'element-plus';
import TenantLayout from '@/layouts/TenantLayout.vue';
import PageHeader from '@/components/PageHeader.vue';

defineOptions({ layout: TenantLayout });

const form = useForm({ name: '', sort: 0 });

function submit() {
  form.post('/tenant/categories');
}
function cancel() { router.visit('/tenant/categories'); }
</script>

<template>
  <Head title="新建分类" />
  <PageHeader title="新建商品分类" />
  <div class="bg-white rounded-md p-6 shadow-[var(--shadow-card)]" style="max-width: 560px">
    <ElForm label-width="100px">
      <ElFormItem label="分类名称" :error="form.errors.name" required>
        <ElInput v-model="form.name" maxlength="100" show-word-limit />
      </ElFormItem>
      <ElFormItem label="排序" :error="form.errors.sort">
        <ElInputNumber v-model="form.sort" :min="0" />
      </ElFormItem>
      <ElFormItem>
        <ElButton type="primary" :loading="form.processing" @click="submit">保存</ElButton>
        <ElButton @click="cancel">取消</ElButton>
      </ElFormItem>
    </ElForm>
  </div>
</template>
