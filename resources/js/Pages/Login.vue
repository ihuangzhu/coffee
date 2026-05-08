<template>
  <div class="min-h-screen flex items-center justify-center bg-stone-50">
    <el-card class="w-96">
      <h1 class="text-2xl font-semibold mb-6 text-stone-800">咖啡 SaaS 登录</h1>
      <el-form @submit.prevent="login">
        <el-form-item label="手机号">
          <el-input v-model="phone" placeholder="13800000000" />
        </el-form-item>
        <el-form-item label="密码">
          <el-input v-model="password" type="password" show-password />
        </el-form-item>
        <el-button type="primary" native-type="submit" :loading="busy" class="w-full">
          登录
        </el-button>
        <div v-if="error" class="text-red-500 mt-3 text-sm">{{ error }}</div>
      </el-form>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import axios from '../lib/axios';
import { useAuthStore } from '../stores/auth';
import { router } from '@inertiajs/vue3';

const auth = useAuthStore();
const phone = ref('');
const password = ref('');
const busy = ref(false);
const error = ref('');

async function login() {
  busy.value = true;
  error.value = '';
  try {
    const { data } = await axios.post('/api/auth/login', {
      phone: phone.value, password: password.value,
    });
    auth.setToken(data.token);
    auth.user = data.user;
    router.visit('/select-tenant');
  } catch (e: unknown) {
    const err = e as { response?: { data?: { errors?: { phone?: string[] } } } };
    error.value = err?.response?.data?.errors?.phone?.[0] ?? '登录失败';
  } finally {
    busy.value = false;
  }
}
</script>
