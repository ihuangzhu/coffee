<script setup lang="ts">
import { useForm, Head } from '@inertiajs/vue3';
import GuestLayout from '@/layouts/GuestLayout.vue';

// 总后台登录页：使用 GuestLayout 提供 mesh 背景 + 居中容器
defineOptions({ layout: GuestLayout });

// 总后台登录表单：phone + password，提交至 /platform/login
const form = useForm({ phone: '', password: '' });

/**
 * 提交登录请求。后端 platform_admin_web guard 校验后写入 session；
 * onFinish 清空 password 避免明文残留。
 */
function submit() {
  form.post('/platform/login', { onFinish: () => form.reset('password') });
}
</script>

<template>
  <Head title="总后台登录" />
  <form class="w-[400px] bg-white rounded-lg p-8 shadow" @submit.prevent="submit">
    <h1 class="text-xl font-semibold mb-6">总后台登录</h1>
    <label class="block mb-2 text-sm">手机号</label>
    <input v-model="form.phone" type="text" autofocus required
           class="w-full border rounded px-3 py-2 mb-4">
    <p v-if="form.errors.phone" class="text-red-600 text-sm -mt-3 mb-3">
      {{ form.errors.phone }}
    </p>
    <label class="block mb-2 text-sm">密码</label>
    <input v-model="form.password" type="password" required
           class="w-full border rounded px-3 py-2 mb-4">
    <button type="submit" :disabled="form.processing"
            class="w-full bg-[var(--color-primary)] text-white py-2 rounded hover:opacity-90">
      登录
    </button>
  </form>
</template>
