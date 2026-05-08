<template>
  <AppLayout>
    <div class="max-w-2xl mx-auto">
      <h1 class="text-3xl font-semibold text-stone-800 mb-4">欢迎</h1>
      <p class="text-stone-600">
        当前租户 ID：<code>{{ auth.tenantId ?? '未选择' }}</code>
      </p>
      <p v-if="!auth.tenantId" class="text-amber-700 mt-2">
        请先 <a href="/select-tenant" class="underline">选择租户</a>
      </p>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '../Layouts/AppLayout.vue';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
</script>
