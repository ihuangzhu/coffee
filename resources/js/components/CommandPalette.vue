<script setup lang="ts">
/**
 * CommandPalette：mod+k 触发的命令面板。
 * Slate × Indigo 视觉升级：白底 card + indigo-soft 选中态 + slate-50 footer 状态条。
 * 行为不变：上下键选中、回车执行、esc 关闭、模糊匹配 label。
 */
import { ref, computed, watch, nextTick } from 'vue';
import { useCommandsStore } from '@/stores/commands';
import { storeToRefs } from 'pinia';
import { useI18n } from 'vue-i18n';
import { ElDialog } from 'element-plus';

const store = useCommandsStore();
const { visible } = storeToRefs(store);
const { t } = useI18n();
const query = ref('');
const focused = ref(0);
const inputRef = ref<HTMLInputElement | null>(null);

const all = computed(() => store.buildAll());
const matched = computed(() => {
  const q = query.value.trim().toLowerCase();
  if (!q) return all.value.slice(0, 30);
  return all.value
    .filter((c) => translate(c.label, t).toLowerCase().includes(q))
    .slice(0, 30);
});

function translate(label: string, tFn: (k: string) => string): string {
  return label.split(' > ').map((p) => {
    try { return tFn(p); } catch { return p; }
  }).join(' > ');
}

watch(visible, async (v) => {
  if (v) {
    query.value = '';
    focused.value = 0;
    await nextTick();
    inputRef.value?.focus();
  }
});

function exec(idx: number) {
  const cmd = matched.value[idx];
  if (!cmd) return;
  cmd.handler();
  store.close();
}

function onKey(e: KeyboardEvent) {
  if (e.key === 'ArrowDown') { e.preventDefault(); focused.value = Math.min(focused.value + 1, matched.value.length - 1); }
  else if (e.key === 'ArrowUp') { e.preventDefault(); focused.value = Math.max(focused.value - 1, 0); }
  else if (e.key === 'Enter') { e.preventDefault(); exec(focused.value); }
  else if (e.key === 'Escape') { store.close(); }
}

const groupLabel = (g: string) => g === 'command' ? '命令' : g === 'recent' ? '最近' : '页面';
</script>

<template>
  <el-dialog v-model="visible" :show-close="false" width="640px" top="15vh"
             custom-class="command-palette-dialog">
    <div class="px-5 py-4 border-b flex items-center gap-3" style="border-color: var(--slate-200)">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
           stroke-width="2" style="color: var(--slate-400)">
        <circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" />
      </svg>
      <input
        ref="inputRef" v-model="query"
        placeholder="搜索页面、命令、最近访问..."
        class="bg-transparent text-[15px] outline-none flex-1" style="color: var(--slate-900);"
        @keydown="onKey" />
      <span class="font-mono text-[11px] px-1.5 py-0.5 rounded border"
            style="background: var(--slate-100); color: var(--slate-600); border-color: var(--slate-200);">esc</span>
    </div>
    <ul class="max-h-[440px] overflow-y-auto py-1">
      <li v-if="!matched.length" class="px-5 py-6 text-[13px]" style="color: var(--text-faint)">无匹配</li>
      <li
        v-for="(cmd, idx) in matched" :key="cmd.id"
        class="px-5 py-2.5 text-[13.5px] flex items-center gap-3 cursor-pointer"
        :class="idx === focused
          ? 'bg-[var(--primary-soft)] text-[var(--primary)]'
          : 'text-[var(--slate-900)] hover:bg-[var(--slate-50)]'"
        @click="exec(idx)" @mouseenter="focused = idx">
        <span class="font-mono text-[10.5px] px-1.5 py-0.5 rounded uppercase tracking-wider"
              style="background: var(--slate-100); color: var(--slate-500);">
          {{ groupLabel(cmd.group) }}
        </span>
        <span class="flex-1">{{ translate(cmd.label, t) }}</span>
        <span v-if="cmd.hint" class="font-mono text-[11px]" style="color: var(--slate-400)">{{ cmd.hint }}</span>
      </li>
    </ul>
    <div class="px-5 py-2.5 border-t flex items-center gap-3 text-[11.5px]"
         style="border-color: var(--slate-200); color: var(--slate-500); background: var(--slate-50);">
      <span><kbd class="font-mono text-[11px] px-1 rounded border" style="background: white; border-color: var(--slate-200);">↑</kbd> <kbd class="font-mono text-[11px] px-1 rounded border" style="background: white; border-color: var(--slate-200);">↓</kbd> 选择</span>
      <span><kbd class="font-mono text-[11px] px-1 rounded border" style="background: white; border-color: var(--slate-200);">↵</kbd> 打开</span>
      <span class="ml-auto">⌘K Powered</span>
    </div>
  </el-dialog>
</template>

<style>
.command-palette-dialog .el-dialog__header { display: none; }
.command-palette-dialog .el-dialog__body { padding: 0; }
.command-palette-dialog { border-radius: 12px !important; overflow: hidden; box-shadow: var(--shadow-lg) !important; }
</style>
