<script setup lang="ts">
/**
 * ShortcutsHelp：mod+/ 触发的快捷键速查弹窗。
 * 通过 defineExpose 暴露 open 方法，由 TenantLayout 监听 'ui0:shortcuts-help' 自定义事件后调用。
 * 列表内容来自 useShortcuts 的全局 registry（每次打开重新计算）。
 */
import { ref, computed } from 'vue';
import { listShortcuts } from '@/composables/useShortcuts';
import { ElDialog } from 'element-plus';

const visible = ref(false);
const items = computed(() => listShortcuts());

defineExpose({ open: () => { visible.value = true; } });
</script>

<template>
  <el-dialog v-model="visible" title="快捷键" width="480px">
    <table class="w-full text-sm">
      <tbody>
        <tr v-for="s in items" :key="s.scope + s.combo" class="border-b">
          <td class="py-2 font-mono-num text-[var(--color-text-muted)]">{{ s.combo }}</td>
          <td class="py-2">{{ s.description }}</td>
          <td class="py-2 text-xs text-gray-400">{{ s.scope }}</td>
        </tr>
      </tbody>
    </table>
  </el-dialog>
</template>
