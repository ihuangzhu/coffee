<template>
  <div class="min-h-screen p-8 bg-stone-50">
    <h1 class="text-2xl font-semibold mb-6 text-stone-800">选择租户</h1>
    <div v-if="loading" class="text-stone-500">加载中…</div>
    <div v-else-if="memberships.length === 0" class="text-stone-500">
      你尚未被绑定到任何租户，请联系平台管理员。
    </div>
    <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <el-card v-for="m in memberships" :key="m.tenant_id" shadow="hover"
               class="cursor-pointer hover:border-amber-600" @click="enter(m.tenant_id, m.tenant_name)">
        <div class="text-lg font-medium text-stone-800">{{ m.tenant_name }}</div>
        <div class="text-xs text-stone-500 mt-1">
          {{ m.store_id ? '门店级成员' : '租户级成员' }}
        </div>
      </el-card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import axios from '../lib/axios';
import { useAuthStore } from '../stores/auth';
import { router } from '@inertiajs/vue3';

interface Membership {
  tenant_id: string; tenant_name: string; store_id: string | null;
}

const auth = useAuthStore();
const memberships = ref<Membership[]>([]);
const loading = ref(true);

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/me/memberships');
    memberships.value = data.memberships;
  } finally {
    loading.value = false;
  }
});

function enter(id: string, name: string) {
  auth.setTenant(id, name);
  router.visit('/');
}
</script>
