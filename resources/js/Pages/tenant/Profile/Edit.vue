<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { ElCard, ElForm, ElFormItem, ElInput, ElButton } from 'element-plus';
import { computed } from 'vue';

defineOptions({ layout: TenantLayout });

// 共享 props 中 auth.user 可能不含 phone（HandleInertiaRequests 仅暴露 id/name/email/is_platform_admin）
// 此处用 any cast 容错；实际 phone 字段从 props.auth.user 取不到时回退空串，保存时由后端 validate 兜底
const page = usePage();
const user = computed(() => (page.props as any).auth?.user ?? { name: '', phone: '' });

const profile = useForm({
  name: user.value.name ?? '',
  phone: user.value.phone ?? '',
});
const pwd = useForm({ current_password: '', password: '', password_confirmation: '' });

function saveProfile() {
  profile.patch('/tenant/profile');
}

function savePassword() {
  pwd.patch('/tenant/profile/password', { onSuccess: () => pwd.reset() });
}
</script>

<template>
  <Head title="个人资料" />

  <div class="max-w-xl space-y-4 mt-3">
    <ElCard shadow="never">
      <template #header><span class="text-[14px] font-semibold">基本信息</span></template>
      <ElForm label-position="top">
        <ElFormItem label="姓名" :error="profile.errors.name">
          <ElInput v-model="profile.name" />
        </ElFormItem>
        <ElFormItem label="手机号" :error="profile.errors.phone">
          <ElInput v-model="profile.phone" />
        </ElFormItem>
        <ElButton type="primary" :loading="profile.processing" @click="saveProfile">保存</ElButton>
      </ElForm>
    </ElCard>

    <ElCard shadow="never">
      <template #header><span class="text-[14px] font-semibold">修改密码</span></template>
      <ElForm label-position="top">
        <ElFormItem label="当前密码" :error="pwd.errors.current_password">
          <ElInput v-model="pwd.current_password" type="password" show-password />
        </ElFormItem>
        <ElFormItem label="新密码" :error="pwd.errors.password">
          <ElInput v-model="pwd.password" type="password" show-password />
        </ElFormItem>
        <ElFormItem label="确认新密码">
          <ElInput v-model="pwd.password_confirmation" type="password" show-password />
        </ElFormItem>
        <ElButton type="primary" :loading="pwd.processing" @click="savePassword">修改密码</ElButton>
      </ElForm>
    </ElCard>
  </div>
</template>
