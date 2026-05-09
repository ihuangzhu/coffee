<script setup lang="ts">
import { usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * UserDropdown：TopBar 右上角用户头像 + 下拉菜单。
 * 租户后台版本，菜单项：个人资料、退出登录。
 * PlatformLayout 不复用此组件（logout 路径不同，自实现内联 dropdown）。
 */
const page = usePage();
const user = computed(() => page.props.auth.user);

function logout() { router.post('/tenant/logout'); }
</script>

<template>
  <el-dropdown v-if="user" trigger="click">
    <button class="flex items-center gap-2 h-9 px-1.5 rounded-md hover:bg-[var(--slate-100)] transition-colors">
      <span class="w-7 h-7 rounded-full grid place-items-center text-white text-[13px] font-medium"
            :style="{ background: 'var(--primary)' }">
        {{ user.name.charAt(0) }}
      </span>
      <span class="text-[13px] font-medium" :style="{ color: 'var(--text)' }">{{ user.name }}</span>
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
           stroke="currentColor" stroke-width="2" :style="{ color: 'var(--text-faint)' }">
        <path d="m6 9 6 6 6-6" />
      </svg>
    </button>
    <template #dropdown>
      <el-dropdown-menu>
        <el-dropdown-item @click="router.visit('/tenant/profile')">个人资料</el-dropdown-item>
        <el-dropdown-item divided @click="logout">退出登录</el-dropdown-item>
      </el-dropdown-menu>
    </template>
  </el-dropdown>
</template>
