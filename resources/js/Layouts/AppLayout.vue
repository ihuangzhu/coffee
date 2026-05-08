<template>
  <div>
    <header class="bg-amber-700 text-white px-6 py-3 flex items-center justify-between">
      <div class="font-semibold">{{ appName ?? 'Coffee' }}</div>
      <div class="text-sm flex items-center gap-4">
        <span v-if="auth.tenantId">当前租户：{{ auth.tenantId }}</span>
        <el-button size="small" @click="switchTenant">切换租户</el-button>
        <el-button size="small" @click="logout">登出</el-button>
      </div>
    </header>
    <main class="p-8">
      <slot />
    </main>
  </div>
</template>

<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import axios from '../lib/axios';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const page = usePage();
const appName = (page.props as Record<string, unknown>).appName;

function switchTenant() {
  auth.clearTenant();
  router.visit('/select-tenant');
}

async function logout() {
  try {
    await axios.post('/api/auth/logout');
  } catch (_) { /* ignore */ }
  auth.logout();
  router.visit('/login');
}
</script>
