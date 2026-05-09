<script setup lang="ts">
/**
 * TabBar：租户/总后台标签栏。
 * - 高 40px，激活 tab 走底部 2px indigo 下划线
 * - 未保存指示：amber 圆点
 * - 中键关闭、右键上下文菜单（关闭其他 / 右侧 / 全部 / 钉住）
 */
import { useTabsStore } from '@/stores/tabs';
import { storeToRefs } from 'pinia';
import { router } from '@inertiajs/vue3';
import { ref, computed, onBeforeUnmount } from 'vue';
import { Refresh } from '@element-plus/icons-vue';

const store = useTabsStore();
const { tabs, activeKey } = storeToRefs(store);

function activate(key: string) {
  store.activate(key);
  router.visit(key, { preserveState: true, preserveScroll: true });
}

/**
 * 刷新过程中按钮上的反馈：图标自旋 + 禁用点击。
 * Inertia 顶部 progress bar 已通过 app.ts 中的 progress 选项接管全局反馈。
 * 这里在按钮自身做反馈，因为顶部 bar 在视觉上离 tab 较远，用户看了刷新按钮后期待按钮本身有变化。
 */
const refreshing = ref(false);
const MIN_SPIN_MS = 400; // 即便后端瞬间返回，也至少转一圈避免闪烁

/**
 * 刷新指定 tab 对应的页面：先激活该 tab（如非当前），然后用 Inertia 强制重载。
 * preserveState=false 让页面 props 重新拉取；onFinish 关闭 refreshing 让图标停转。
 */
function refresh(key: string) {
  if (refreshing.value) return;
  refreshing.value = true;
  const startedAt = Date.now();
  const stop = () => {
    const wait = Math.max(0, MIN_SPIN_MS - (Date.now() - startedAt));
    setTimeout(() => { refreshing.value = false; }, wait);
  };
  if (activeKey.value !== key) {
    store.activate(key);
    router.visit(key, { preserveState: false, preserveScroll: true, onFinish: stop });
    return;
  }
  router.reload({ onFinish: stop });
}

function refreshActive() {
  if (activeKey.value) refresh(activeKey.value);
}

function close(e: MouseEvent, key: string) {
  e.stopPropagation();
  if (store.close(key) && store.activeKey) {
    router.visit(store.activeKey, { preserveState: true, preserveScroll: true });
  }
}

function onMiddleClick(e: MouseEvent, key: string) {
  if (e.button === 1) { e.preventDefault(); close(e, key); }
}

// 右键菜单
const menu = ref<{ key: string; x: number; y: number } | null>(null);
const menuTab = computed(() => tabs.value.find((t) => t.key === menu.value?.key) ?? null);

function openMenu(e: MouseEvent, key: string) {
  e.preventDefault();
  menu.value = { key, x: e.clientX, y: e.clientY };
}
function closeMenu() { menu.value = null; }

function act(fn: () => void) {
  fn();
  closeMenu();
  if (store.activeKey) {
    router.visit(store.activeKey, { preserveState: true, preserveScroll: true });
  }
}

window.addEventListener('click', closeMenu);
window.addEventListener('blur', closeMenu);
onBeforeUnmount(() => {
  window.removeEventListener('click', closeMenu);
  window.removeEventListener('blur', closeMenu);
});
</script>

<template>
  <div class="h-10 flex items-end px-4 gap-1 border-b overflow-x-auto"
       style="background: var(--surface); border-color: var(--border);">
    <div
      v-for="tab in tabs" :key="tab.key"
      class="h-[30px] px-3 text-[13px] cursor-pointer flex items-center gap-1.5 shrink-0 group transition-colors"
      :class="activeKey === tab.key
        ? 'text-[var(--text)] font-medium border-b-2 border-[var(--accent)]'
        : 'text-[var(--text-muted)] hover:text-[var(--text)] border-b-2 border-transparent'"
      @click="activate(tab.key)"
      @mousedown="onMiddleClick($event, tab.key)"
      @contextmenu="openMenu($event, tab.key)">
      <span v-if="tab.pinned" class="text-[var(--text-faint)]">📌</span>
      <span>{{ tab.title }}</span>
      <span v-if="tab.dirty" class="w-1.5 h-1.5 rounded-full"
            :style="{ background: 'var(--warn)' }" title="未保存" />
      <button
        v-if="!tab.pinned"
        class="ml-0.5 w-4 h-4 leading-none rounded text-[var(--text-faint)] hover:bg-[var(--slate-100)] hover:text-[var(--text-mid)] opacity-0 group-hover:opacity-100"
        @click="close($event, tab.key)">×</button>
    </div>

    <!-- 右侧工具区：固定 sticky 在 tabs 行尾，刷新当前激活 tab 对应页面 -->
    <div class="ml-auto flex items-center pb-[2px] sticky right-0 pl-2"
         :style="{ background: 'var(--surface)' }">
      <el-tooltip content="刷新当前页" placement="bottom" :show-after="300">
        <button
          class="w-7 h-7 grid place-items-center rounded text-[var(--text-muted)] hover:bg-[var(--slate-100)] hover:text-[var(--text)] disabled:opacity-40 disabled:cursor-not-allowed"
          :disabled="!activeKey || refreshing"
          @click="refreshActive">
          <el-icon :size="14" :class="{ 'tab-refresh-spin': refreshing }"><Refresh /></el-icon>
        </button>
      </el-tooltip>
    </div>

    <!-- 上下文菜单 -->
    <div
      v-if="menu && menuTab"
      class="fixed z-50 min-w-[160px] py-1 rounded-md border shadow-lg text-[13px]"
      :style="{
        left: menu.x + 'px',
        top: menu.y + 'px',
        background: 'var(--surface)',
        borderColor: 'var(--border)',
      }"
      @click.stop>
      <button
        class="w-full text-left px-3 py-1.5 hover:bg-[var(--slate-100)]"
        @click="closeMenu(); refresh(menu!.key)">刷新</button>
      <div class="my-1 border-t" :style="{ borderColor: 'var(--border)' }" />
      <button
        class="w-full text-left px-3 py-1.5 hover:bg-[var(--slate-100)]"
        :disabled="menuTab.pinned"
        :class="{ 'opacity-40 cursor-not-allowed': menuTab.pinned }"
        @click="act(() => store.close(menu!.key))">关闭</button>
      <button
        class="w-full text-left px-3 py-1.5 hover:bg-[var(--slate-100)]"
        @click="act(() => store.closeOthers(menu!.key))">关闭其他</button>
      <button
        class="w-full text-left px-3 py-1.5 hover:bg-[var(--slate-100)]"
        @click="act(() => store.closeRight(menu!.key))">关闭右侧</button>
      <button
        class="w-full text-left px-3 py-1.5 hover:bg-[var(--slate-100)]"
        @click="act(() => store.closeAll())">全部关闭</button>
      <div class="my-1 border-t" :style="{ borderColor: 'var(--border)' }" />
      <button
        class="w-full text-left px-3 py-1.5 hover:bg-[var(--slate-100)]"
        @click="act(() => store.togglePin(menu!.key))">
        {{ menuTab.pinned ? '取消钉住' : '钉住' }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.tab-refresh-spin { animation: tab-refresh-spin 0.7s linear infinite; }
@keyframes tab-refresh-spin {
  from { transform: rotate(0deg); }
  to   { transform: rotate(-360deg); }
}
</style>
