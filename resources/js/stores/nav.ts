import { defineStore } from 'pinia';
import { ref, watch } from 'vue';

const STORAGE_KEY = 'ui:nav.sidebarCollapsed';

/**
 * 侧栏 UI 状态：折叠 + 当前过滤词。
 * - sidebarCollapsed：跨会话持久化在 localStorage（spec §3.2）
 *   选 localStorage 而非 sessionStorage：用户惯于关闭后下次打开仍为收起态；
 *   filter 词不持久化（每次重新打开都从全菜单开始）
 */
export const useNavStore = defineStore('nav', () => {
  /** 从 localStorage 读初始值；首次访问默认 false */
  const initial = (() => {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      return raw === '1';
    } catch { return false; }
  })();

  const sidebarCollapsed = ref(initial);
  const sidebarFilter = ref('');

  /** 写回 localStorage；忽略写失败（隐私模式 / quota 满都不阻塞） */
  watch(sidebarCollapsed, (v) => {
    try { localStorage.setItem(STORAGE_KEY, v ? '1' : '0'); } catch { /* noop */ }
  });

  function toggleSidebar() { sidebarCollapsed.value = !sidebarCollapsed.value; }
  function setFilter(v: string) { sidebarFilter.value = v; }
  function clearFilter() { sidebarFilter.value = ''; }

  return { sidebarCollapsed, sidebarFilter, toggleSidebar, setFilter, clearFilter };
});
