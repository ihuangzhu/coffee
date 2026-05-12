<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue';
import { ElMessage } from 'element-plus';
import UserDropdown from '@/components/UserDropdown.vue';
import StoreSwitcher from '@/components/StoreSwitcher.vue';
import NotificationCenter from '@/components/NotificationCenter.vue';
import TopNav from '@/components/TopNav.vue';
import Sidebar from '@/components/Sidebar.vue';
import TabBar from '@/components/TabBar.vue';
import ShortcutsHelp from '@/components/ShortcutsHelp.vue';
import CommandPalette from '@/components/CommandPalette.vue';
import { useNavStore } from '@/stores/nav';
import { storeToRefs } from 'pinia';
import { useCommandsStore } from '@/stores/commands';

/**
 * TenantLayout：租户后台主 layout。Slate × Indigo SaaS 视觉。
 * - 全浅色：白 TopBar + 白 Sidebar + slate-50 内容区
 * - TopBar 顺序：Brand · StoreSwitcher · TopNav · │ · 搜索按钮 · NotificationCenter · UserDropdown
 * - Sidebar 支持折叠（240↔64），状态由 useNavStore 管理 + localStorage 持久化（E1）
 */
const page = usePage();
const flash = computed(() => page.props.flash);

function showFlash() {
  if (flash.value.success) ElMessage.success(flash.value.success);
  if (flash.value.error) ElMessage.error(flash.value.error);
  if (flash.value.info) ElMessage.info(flash.value.info);
}
onMounted(showFlash);
watch(flash, showFlash, { deep: true });

const helpRef = ref<{ open: () => void } | null>(null);
function onOpenHelp() { helpRef.value?.open(); }
onMounted(() => window.addEventListener('ui0:shortcuts-help', onOpenHelp));
onBeforeUnmount(() => window.removeEventListener('ui0:shortcuts-help', onOpenHelp));

/** 折叠态由 nav store 提供，sidebar 容器据此切换 width */
const nav = useNavStore();
const { sidebarCollapsed } = storeToRefs(nav);

/** 搜索按钮直接 open command palette（与 ⌘K 等价） */
const cmd = useCommandsStore();
function openPalette() { cmd.open(); }
</script>

<template>
  <div class="tenant-layout min-h-screen flex flex-col" style="background: var(--bg)">
    <!-- TopBar：56px 白底 -->
    <header class="h-14 flex items-center px-5 gap-4 border-b shrink-0"
            style="background: var(--topbar); border-color: var(--border);">
      <!-- Brand -->
      <div class="flex items-center gap-2.5">
        <div class="w-7 h-7 rounded-md grid place-items-center font-bold text-white text-[14px]"
             :style="{ background: 'var(--primary)' }">S</div>
        <div class="text-[15px] font-semibold tracking-tight" :style="{ color: 'var(--text)' }">
          Supermarket
        </div>
      </div>

      <StoreSwitcher />
      <TopNav scope="tenant" class="ml-2" />

      <div class="flex-1" />

      <!-- 搜索按钮 → ⌘K -->
      <button
        class="flex items-center gap-2 h-8 px-3 rounded-md border text-[13px] hover:border-[var(--slate-300)]"
        style="background: var(--surface); border-color: var(--border); color: var(--text-muted);"
        @click="openPalette">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" />
        </svg>
        <span>搜索 / 跳转 / 命令</span>
        <span class="font-mono text-[11px] px-1 py-px rounded border"
              style="background: var(--slate-100); color: var(--text-mid); border-color: var(--slate-200);">
          ⌘K
        </span>
      </button>

      <NotificationCenter />
      <UserDropdown />
    </header>

    <div class="flex-1 flex overflow-hidden">
      <aside class="shrink-0 overflow-y-auto border-r transition-[width] duration-150"
             :class="sidebarCollapsed ? 'w-16' : 'w-60'"
             style="background: var(--sidebar); border-color: var(--border);"
             data-testid="sidebar-placeholder">
        <Sidebar scope="tenant" />
      </aside>

      <main class="flex-1 overflow-auto flex flex-col">
        <TabBar />
        <div class="flex-1 flex flex-col min-h-0 w-full px-6 pb-4">
          <slot />
        </div>
      </main>
    </div>

    <ShortcutsHelp ref="helpRef" />
    <CommandPalette />
  </div>
</template>
