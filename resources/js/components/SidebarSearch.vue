<script setup lang="ts">
/**
 * 侧栏菜单搜索框：双向绑定 navStore.sidebarFilter，Esc 清空。
 * 仅租户后台 sidebar 顶部展示（总后台菜单短，不需要搜索）。
 */
import { useNavStore } from '@/stores/nav';
import { storeToRefs } from 'pinia';

const store = useNavStore();
const { sidebarFilter } = storeToRefs(store);

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape') store.clearFilter();
}
</script>

<template>
  <div class="px-3 pt-3 pb-2">
    <input v-model="sidebarFilter" placeholder="筛选菜单"
           class="w-full h-8 px-2 text-sm border border-[var(--color-border)] rounded bg-white"
           @keydown="onKeydown">
  </div>
</template>
