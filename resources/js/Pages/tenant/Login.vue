<script setup lang="ts">
import { useForm, Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import GuestLayout from '@/layouts/GuestLayout.vue';

// 租户后台登录页：使用 GuestLayout 提供 mesh 背景 + 居中容器 + flash toast
defineOptions({ layout: GuestLayout });

// vue-i18n：用 t() 替换硬编码中文（Phase H3 演示性接入，详细 i18n 化在 UI-1+ 各页面继续）
const { t } = useI18n();

// 租户后台登录表单：phone + password
const form = useForm({ phone: '', password: '', remember: false });

/**
 * 提交登录请求。成功跳转交由后端 redirect 处理；
 * 无论成功失败都清空 password，避免明文残留在表单状态。
 */
function submit() {
  form.post('/tenant/login', { onFinish: () => form.reset('password') });
}
</script>

<template>
  <Head :title="t('auth.tenant_login_title')" />
  <form class="w-[400px] bg-white rounded-lg p-8 shadow" @submit.prevent="submit">
    <h1 class="text-xl font-semibold mb-6">{{ t('auth.tenant_login_title') }}</h1>
    <label class="block mb-2 text-sm">{{ t('auth.phone') }}</label>
    <input v-model="form.phone" type="text" autofocus required
           class="w-full border rounded px-3 py-2 mb-4">
    <p v-if="form.errors.phone" class="text-red-600 text-sm -mt-3 mb-3">
      {{ form.errors.phone }}
    </p>
    <label class="block mb-2 text-sm">{{ t('auth.password') }}</label>
    <input v-model="form.password" type="password" required
           class="w-full border rounded px-3 py-2 mb-4">
    <label class="flex items-center text-sm mb-4">
      <input v-model="form.remember" type="checkbox" class="mr-2">{{ t('auth.remember') }}
    </label>
    <button type="submit" :disabled="form.processing"
            class="w-full bg-[var(--color-primary)] text-white py-2 rounded hover:opacity-90">
      {{ t('auth.login') }}
    </button>
  </form>
</template>
