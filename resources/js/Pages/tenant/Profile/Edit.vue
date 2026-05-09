<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import TenantLayout from '@/layouts/TenantLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
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
  <PageHeader title="个人资料" />

  <div class="max-w-xl space-y-6">
    <section class="bg-white rounded-md p-6 shadow-[var(--shadow-card)]">
      <h2 class="font-medium mb-4">基本信息</h2>
      <el-form label-position="top">
        <el-form-item label="姓名" :error="profile.errors.name">
          <el-input v-model="profile.name" />
        </el-form-item>
        <el-form-item label="手机号" :error="profile.errors.phone">
          <el-input v-model="profile.phone" />
        </el-form-item>
        <el-button type="primary" :loading="profile.processing" @click="saveProfile">
          保存
        </el-button>
      </el-form>
    </section>

    <section class="bg-white rounded-md p-6 shadow-[var(--shadow-card)]">
      <h2 class="font-medium mb-4">修改密码</h2>
      <el-form label-position="top">
        <el-form-item label="当前密码" :error="pwd.errors.current_password">
          <el-input v-model="pwd.current_password" type="password" show-password />
        </el-form-item>
        <el-form-item label="新密码" :error="pwd.errors.password">
          <el-input v-model="pwd.password" type="password" show-password />
        </el-form-item>
        <el-form-item label="确认新密码">
          <el-input v-model="pwd.password_confirmation" type="password" show-password />
        </el-form-item>
        <el-button type="primary" :loading="pwd.processing" @click="savePassword">
          修改密码
        </el-button>
      </el-form>
    </section>
  </div>
</template>
