<script setup lang="ts">
/**
 * Sidebar：根据 useMenu(scope) 渲染当前一级模块对应的二级菜单树。
 * - 租户后台：顶部展示 SidebarSearch 模糊过滤（白色 sidebar）
 * - 总后台：顶部展示 SidebarSearch 模糊过滤（slate-900 sidebar，深色样式由 .platform-layout 注入）
 * - 当前页激活态：用 window.location.pathname 前缀匹配
 * - 折叠态：useNavStore.sidebarCollapsed=true 时容器宽 64，仅显示图标
 *   折叠态下数字徽章/标签隐藏，hover 时由 el-tooltip 提示菜单名
 */
import { computed, markRaw, type Component } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useMenu } from '@/composables/useMenu';
import { useNavStore } from '@/stores/nav';
import { storeToRefs } from 'pinia';
import type { MenuItem } from '@/composables/useNavigation';
import SidebarSearch from './SidebarSearch.vue';
import {
  Avatar, Box, Coffee, DataAnalysis, Document, Files, Folder, Goods,
  Histogram, House, Key, List, Management, Menu as MenuIcon, Money,
  Notebook, Odometer, OfficeBuilding, PieChart, Promotion, Setting,
  Shop, ShoppingCart, Tickets, Tools, TrendCharts, User, UserFilled,
} from '@element-plus/icons-vue';

const iconMap: Record<string, Component> = {
  Avatar, Box, Coffee, DataAnalysis, Document, Files, Folder, Goods,
  Histogram, House, Key, List, Management, Menu: MenuIcon, Money,
  Notebook, Odometer, OfficeBuilding, PieChart, Promotion, Setting,
  Shop, ShoppingCart, Tickets, Tools, TrendCharts, User, UserFilled,
};
function resolveIcon(name?: string): Component {
  return markRaw(iconMap[name ?? ''] ?? MenuIcon);
}

const props = defineProps<{ scope: 'tenant' | 'platform' }>();
const { sidebarMenu } = useMenu(props.scope);
const { t } = useI18n();
const nav = useNavStore();
const { sidebarFilter, sidebarCollapsed } = storeToRefs(nav);
const page = usePage();

function matches(item: MenuItem, q: string): boolean {
  if (!q) return true;
  const label = t(item.label).toLowerCase();
  if (label.includes(q.toLowerCase())) return true;
  return (item.children ?? []).some((c) => matches(c, q));
}

const filtered = computed<MenuItem[]>(() =>
  sidebarMenu.value
    .filter((i) => matches(i, sidebarFilter.value))
    .map((i) => ({
      ...i,
      children: i.children?.filter((c) => matches(c, sidebarFilter.value)),
    })),
);

function isActive(url?: string): boolean {
  void page.url;
  return !!url && window.location.pathname.startsWith(url);
}

function toggleCollapsed() { nav.toggleSidebar(); }
</script>

<template>
  <div class="flex flex-col h-full">
    <SidebarSearch v-if="!sidebarCollapsed" />

    <div class="flex-1 overflow-y-auto py-2">
      <ul class="px-2">
        <li v-for="item in filtered" :key="item.key">
          <el-tooltip v-if="sidebarCollapsed" :content="t(item.label)" placement="right" :show-after="200">
            <button
              class="w-full h-8 grid place-items-center rounded text-[13.5px] mb-0.5 transition-colors"
              :class="isActive(item.url)
                ? (scope === 'platform' ? 'platform-side-active' : 'tenant-side-active')
                : (scope === 'platform' ? 'platform-side-idle' : 'tenant-side-idle')"
              @click="item.url && router.visit(item.url)">
              <el-icon :size="16"><component :is="resolveIcon(item.icon)" /></el-icon>
            </button>
          </el-tooltip>

          <button v-else
            class="w-full flex items-center gap-2 text-left h-8 px-3 rounded text-[13.5px] mb-0.5 transition-colors"
            :class="isActive(item.url)
              ? (scope === 'platform' ? 'platform-side-active' : 'tenant-side-active')
              : (scope === 'platform' ? 'platform-side-idle' : 'tenant-side-idle')"
            @click="item.url && router.visit(item.url)">
            <el-icon :size="15" class="opacity-90"><component :is="resolveIcon(item.icon)" /></el-icon>
            <span class="flex-1">{{ t(item.label) }}</span>
          </button>

          <ul v-if="!sidebarCollapsed && item.children?.length" class="ml-3 border-l pl-2"
              :style="{ borderColor: scope === 'platform' ? 'var(--side-border)' : 'var(--border-soft)' }">
            <li v-for="child in item.children" :key="child.key">
              <button
                class="w-full flex items-center gap-2 text-left h-7 px-3 rounded text-[12.5px] transition-colors"
                :class="isActive(child.url)
                  ? (scope === 'platform' ? 'platform-side-active' : 'tenant-side-active')
                  : (scope === 'platform' ? 'platform-side-idle' : 'tenant-side-idle')"
                @click="child.url && router.visit(child.url)">
                <el-icon v-if="child.icon" :size="13" class="opacity-80"><component :is="resolveIcon(child.icon)" /></el-icon>
                <span class="flex-1">{{ t(child.label) }}</span>
              </button>
            </li>
          </ul>
        </li>
      </ul>
    </div>

    <!-- 折叠按钮 footer -->
    <div class="shrink-0 px-3 py-3 border-t"
         :style="{ borderColor: scope === 'platform' ? 'var(--side-border)' : 'var(--border-soft)' }">
      <button class="w-full h-8 flex items-center justify-center rounded text-[12px] transition-colors"
              :class="scope === 'platform' ? 'platform-side-idle' : 'tenant-side-idle'"
              @click="toggleCollapsed">
        <svg v-if="sidebarCollapsed" width="14" height="14" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
        <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
        <span v-if="!sidebarCollapsed" class="ml-2">收起侧栏</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
/* 租户后台浅色侧栏配色 */
.tenant-side-idle  { color: var(--text-mid); }
.tenant-side-idle:hover { background: var(--slate-100); color: var(--text); }
.tenant-side-active { background: var(--primary-soft); color: var(--primary); font-weight: 500; }

/* 总后台深色侧栏配色 */
.platform-side-idle { color: var(--side-text-mid); }
.platform-side-idle:hover { background: var(--side-hover); color: var(--side-text); }
.platform-side-active { background: var(--side-active-bg); color: var(--side-active-text); font-weight: 500; }
</style>
