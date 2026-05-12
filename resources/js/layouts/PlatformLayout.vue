<script setup lang="ts">
import { usePage, router } from '@inertiajs/vue3';
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue';
import { ElMessage } from 'element-plus';
import TopNav from '@/components/TopNav.vue';
import Sidebar from '@/components/Sidebar.vue';
import TabBar from '@/components/TabBar.vue';
import ShortcutsHelp from '@/components/ShortcutsHelp.vue';
import CommandPalette from '@/components/CommandPalette.vue';
import EnvBadge from '@/components/EnvBadge.vue';
import HealthPill from '@/components/HealthPill.vue';
import NotificationCenter from '@/components/NotificationCenter.vue';
import { useNavStore } from '@/stores/nav';
import { storeToRefs } from 'pinia';
import { useCommandsStore } from '@/stores/commands';

/**
 * PlatformLayout：总后台主 layout。Slate × Indigo SaaS 视觉。
 * - 浅色 TopBar + 浅色内容区 + 深色 slate-900 侧栏（差异化标识）
 * - TopBar 标识：品牌字 + 'Console' tag + EnvBadge + HealthPill
 * - 不含 TenantSwitcher（总后台无 current tenant 概念）
 * - 用户下拉直接内联（logout → /platform/logout，与 UserDropdown 路径不同）
 */
const page = usePage();
const flash = computed(() => page.props.flash);
const user = computed(() => page.props.auth.user);

function showFlash() {
  if (flash.value.success) ElMessage.success(flash.value.success);
  if (flash.value.error) ElMessage.error(flash.value.error);
  if (flash.value.info) ElMessage.info(flash.value.info);
}
onMounted(showFlash);
watch(flash, showFlash, { deep: true });

function logout() { router.post('/platform/logout'); }

const helpRef = ref<{ open: () => void } | null>(null);
function onOpenHelp() { helpRef.value?.open(); }
onMounted(() => window.addEventListener('ui0:shortcuts-help', onOpenHelp));
onBeforeUnmount(() => window.removeEventListener('ui0:shortcuts-help', onOpenHelp));

const nav = useNavStore();
const { sidebarCollapsed } = storeToRefs(nav);

const cmd = useCommandsStore();
function openPalette() { cmd.open(); }
</script>

<template>
  <div class="platform-layout min-h-screen flex flex-col" style="background: var(--bg)">
    <header class="h-14 flex items-center px-5 gap-4 border-b shrink-0"
            style="background: var(--topbar); border-color: var(--border);">
      <!-- Brand + Console tag -->
      <div class="flex items-center gap-2.5">
        <div class="w-7 h-7 rounded-md grid place-items-center font-bold text-white text-[14px]"
             :style="{ background: 'var(--primary)' }">S</div>
        <div class="text-[15px] font-semibold tracking-tight" :style="{ color: 'var(--text)' }">
          Supermarket
        </div>
        <span class="font-mono text-[10.5px] font-medium tracking-[.04em] px-2 py-[3px] rounded ml-1 border"
              style="background: var(--primary-soft); color: var(--primary); border-color: var(--primary-soft-2);">
          Console
        </span>
      </div>

      <EnvBadge class="ml-2" />

      <TopNav scope="platform" class="ml-4" />

      <div class="flex-1" />

      <HealthPill />

      <button
        class="flex items-center gap-2 h-8 px-3 rounded-md border text-[13px] hover:border-[var(--slate-300)]"
        style="background: var(--surface); border-color: var(--border); color: var(--text-muted);"
        @click="openPalette">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" />
        </svg>
        <span>命令 / 跳转</span>
        <span class="font-mono text-[11px] px-1 py-px rounded border"
              style="background: var(--slate-100); color: var(--text-mid); border-color: var(--slate-200);">
          ⌘K
        </span>
      </button>

      <NotificationCenter />

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
            <el-dropdown-item divided @click="logout">退出登录</el-dropdown-item>
          </el-dropdown-menu>
        </template>
      </el-dropdown>
    </header>

    <div class="flex-1 flex overflow-hidden">
      <aside class="shrink-0 overflow-y-auto border-r transition-[width] duration-150"
             :class="sidebarCollapsed ? 'w-16' : 'w-60'"
             style="background: var(--sidebar);"
             data-testid="sidebar-placeholder">
        <Sidebar scope="platform" />
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
