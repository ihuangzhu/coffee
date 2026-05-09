<script setup lang="ts">
/**
 * TopNav：一级模块导航条。
 * - 根据 scope（租户/总后台）选择不同模块定义
 * - 按用户权限过滤后渲染按钮
 * - 高亮当前模块：active 状态下方 2px indigo 下划线（与 spec §3.1 对齐）
 * - 点击跳转该模块的 defaultUrl
 */
import { router } from '@inertiajs/vue3';
import { useMenu } from '@/composables/useMenu';
import { useI18n } from 'vue-i18n';

const props = defineProps<{ scope: 'tenant' | 'platform' }>();
const { modules, currentModule } = useMenu(props.scope);
const { t } = useI18n();

function go(url: string) { router.visit(url); }
</script>

<template>
  <nav class="flex items-center gap-7 text-sm">
    <button
      v-for="m in modules" :key="m.key"
      class="relative h-14 font-medium text-[13.5px] transition-colors"
      :class="currentModule?.key === m.key
        ? 'text-[var(--text)] after:content-[\'\'] after:absolute after:inset-x-0 after:bottom-0 after:h-[2px] after:bg-[var(--accent)] after:rounded-t'
        : 'text-[var(--text-muted)] hover:text-[var(--text)]'"
      @click="go(m.defaultUrl)">
      {{ t(m.label) }}
    </button>
  </nav>
</template>
