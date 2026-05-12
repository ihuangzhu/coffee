<script setup lang="ts">
/**
 * 新建租户 + 初始店主表单。提交后端会在事务里同时建 tenant + user + tenant_user(店主)。
 * owner_phone 已存在的 user 会被复用，name/password 不重置。
 */
import { Head, router, useForm } from '@inertiajs/vue3';
import PlatformLayout from '@/layouts/PlatformLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { ElButton, ElForm, ElFormItem, ElInput, ElCard } from 'element-plus';

defineOptions({ layout: PlatformLayout });

const form = useForm({
  tenant_name: '',
  owner_name: '',
  owner_phone: '',
  owner_password: '',
});

function submit() { form.post('/platform/tenants'); }
function cancel() { router.visit('/platform/tenants'); }
</script>

<template>
  <Head title="新建租户" />
  <PageHeader :breadcrumb="[{ label: '租户管理', to: '/platform/tenants' }, { label: '新建' }]">
    <template #actions>
      <ElButton @click="cancel">取消</ElButton>
      <ElButton type="primary" :loading="form.processing" @click="submit">创建</ElButton>
    </template>
  </PageHeader>
  <ElCard shadow="never" class="mt-3 max-w-[640px]">
    <ElForm label-width="120px">
      <ElFormItem label="租户名称" :error="form.errors.tenant_name">
        <ElInput v-model="form.tenant_name" maxlength="100" show-word-limit style="width: 360px" />
      </ElFormItem>
      <ElFormItem label="店主姓名" :error="form.errors.owner_name">
        <ElInput v-model="form.owner_name" maxlength="50" style="width: 360px" />
      </ElFormItem>
      <ElFormItem label="店主手机号" :error="form.errors.owner_phone">
        <ElInput v-model="form.owner_phone" maxlength="20" style="width: 360px" />
      </ElFormItem>
      <ElFormItem label="初始密码" :error="form.errors.owner_password">
        <ElInput v-model="form.owner_password" type="password" show-password style="width: 360px" />
        <div class="text-[12px] mt-1" style="color: var(--text-muted)">至少 8 位；如手机号已存在，将复用该账号且不重置密码。</div>
      </ElFormItem>
    </ElForm>
  </ElCard>
</template>
