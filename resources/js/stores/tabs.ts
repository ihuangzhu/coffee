import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

/**
 * Tab：单个租户后台标签页元数据。
 * - key: 完整 URL（含 query），唯一标识；同 URL 多次 navigate 仅激活既有 tab
 * - title: 标签显示名（由 deriveTitleFromUrl 推断或后续命令面板/路由元定义提供）
 * - pinned: 钉住的 tab 不可关闭、不参与 LRU 淘汰、不被 closeOthers 移除
 * - dirty: 未保存改动（关闭前需确认）；FormDialog/PageHeader 在 form 状态变更时调用 markDirty
 * - openedAt / lastActiveAt: 时间戳，淘汰策略依据 lastActiveAt 升序
 */
export interface Tab {
  key: string;
  title: string;
  pinned: boolean;
  dirty: boolean;
  openedAt: number;
  lastActiveAt: number;
}

const STORAGE_KEY = 'ui0:tabs';
const MAX_TABS = 12;
const MAX_RECENT = 10;

/** 写 sessionStorage（本浏览器 tab 范围；新开窗口不继承） */
function persist(state: { tabs: Tab[]; activeKey: string | null }) {
  try {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state));
  } catch {
    // sessionStorage 不可用（隐私模式）静默忽略，下次刷新视为干净状态
  }
}

/** 从 sessionStorage 恢复状态；解析失败回落空状态 */
function restore(): { tabs: Tab[]; activeKey: string | null } {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY);
    if (!raw) return { tabs: [], activeKey: null };
    return JSON.parse(raw);
  } catch {
    return { tabs: [], activeKey: null };
  }
}

/**
 * TabsStore：租户后台 TabBar 状态机。
 * - 上限 12，超限时按 lastActiveAt 升序淘汰非 pinned 非 dirty
 * - 关闭最近 10 个保留至 recentlyClosed，支持 mod+shift+t 重开
 * - 所有写操作即时持久化至 sessionStorage
 */
export const useTabsStore = defineStore('tabs', () => {
  const restored = restore();
  const tabs = ref<Tab[]>(restored.tabs);
  const activeKey = ref<string | null>(restored.activeKey);
  const recentlyClosed = ref<Tab[]>([]);

  /** keep-alive include 候选（UI-0 暂未启用真正的 <keep-alive>，留给 spec §13 后续优化） */
  const cachedKeys = computed(() => tabs.value.map((t) => t.key));

  /**
   * 打开（或激活）tab。
   * 同 key 已存在 -> 仅刷新 lastActiveAt 并激活。
   * 新 key + 已达上限 -> 先淘汰一个最久未活跃且非 pinned 非 dirty 的 tab。
   */
  function open(tab: Omit<Tab, 'openedAt' | 'lastActiveAt' | 'dirty'> & { dirty?: boolean }) {
    const existing = tabs.value.find((t) => t.key === tab.key);
    if (existing) {
      existing.lastActiveAt = Date.now();
      activeKey.value = existing.key;
    } else {
      if (tabs.value.length >= MAX_TABS) {
        evictOldestNonDirtyNonPinned();
      }
      tabs.value.push({
        ...tab,
        dirty: tab.dirty ?? false,
        openedAt: Date.now(),
        lastActiveAt: Date.now(),
      });
      activeKey.value = tab.key;
    }
    save();
  }

  /**
   * 关闭 tab。
   * - pinned 拒绝
   * - dirty 弹 confirm，用户取消则保留并返回 false
   * - 关闭活跃 tab 时尝试激活右侧或左侧邻居
   * 返回 true 表示已成功关闭。
   */
  function close(key: string): boolean {
    const idx = tabs.value.findIndex((t) => t.key === key);
    if (idx === -1) return false;
    const tab = tabs.value[idx];
    if (tab.pinned) return false;
    if (tab.dirty && !window.confirm('该页面有未保存的改动，确认放弃?')) return false;
    tabs.value.splice(idx, 1);
    recentlyClosed.value.unshift(tab);
    if (recentlyClosed.value.length > MAX_RECENT) recentlyClosed.value.pop();
    if (activeKey.value === key) {
      activeKey.value = tabs.value[idx]?.key ?? tabs.value[idx - 1]?.key ?? null;
    }
    save();
    return true;
  }

  function activate(key: string) {
    const tab = tabs.value.find((t) => t.key === key);
    if (!tab) return;
    tab.lastActiveAt = Date.now();
    activeKey.value = key;
    save();
  }

  /**
   * 重开最近关闭的一个 tab。
   * 仅恢复元数据并 open（不直接 router.visit，由调用方决定）。
   * 返回 url（即 key），调用方可用来 router.visit。
   */
  function reopenLast(): string | null {
    const tab = recentlyClosed.value.shift();
    if (!tab) return null;
    open({ key: tab.key, title: tab.title, pinned: false });
    return tab.key;
  }

  function markDirty(key: string) {
    const t = tabs.value.find((x) => x.key === key);
    if (t) { t.dirty = true; save(); }
  }

  function markClean(key: string) {
    const t = tabs.value.find((x) => x.key === key);
    if (t) { t.dirty = false; save(); }
  }

  /** 关闭除指定 key 与 pinned 外的所有 tab */
  function closeOthers(key: string) {
    tabs.value = tabs.value.filter((t) => t.key === key || t.pinned);
    activeKey.value = key;
    save();
  }

  /** 关闭指定 key 右侧所有非 pinned tab */
  function closeRight(key: string) {
    const idx = tabs.value.findIndex((t) => t.key === key);
    if (idx === -1) return;
    tabs.value = [
      ...tabs.value.slice(0, idx + 1),
      ...tabs.value.slice(idx + 1).filter((t) => t.pinned),
    ];
    save();
  }

  /** 关闭全部非 pinned tab；dirty tab 弹一次合并 confirm */
  function closeAll() {
    const dirtyCount = tabs.value.filter((t) => !t.pinned && t.dirty).length;
    if (dirtyCount > 0 && !window.confirm(`有 ${dirtyCount} 个标签未保存，确认全部关闭?`)) return;
    tabs.value = tabs.value.filter((t) => t.pinned);
    activeKey.value = tabs.value[tabs.value.length - 1]?.key ?? null;
    save();
  }

  function togglePin(key: string) {
    const t = tabs.value.find((x) => x.key === key);
    if (t) { t.pinned = !t.pinned; save(); }
  }

  /** 拖拽重排：pinned tab 不参与（保持其相对位置不变） */
  function reorder(fromKey: string, toKey: string) {
    const from = tabs.value.findIndex((t) => t.key === fromKey);
    const to = tabs.value.findIndex((t) => t.key === toKey);
    if (from === -1 || to === -1) return;
    if (tabs.value[from].pinned || tabs.value[to].pinned) return;
    const [moved] = tabs.value.splice(from, 1);
    tabs.value.splice(to, 0, moved);
    save();
  }

  /**
   * 上限淘汰：选择最久未活跃且非 pinned 非 dirty 的 tab 移除。
   * 全部 pinned/dirty 时不淘汰（即允许临时超限，避免用户工作丢失）。
   */
  function evictOldestNonDirtyNonPinned() {
    const candidates = tabs.value
      .filter((t) => !t.pinned && !t.dirty)
      .sort((a, b) => a.lastActiveAt - b.lastActiveAt);
    if (candidates[0]) {
      const i = tabs.value.indexOf(candidates[0]);
      tabs.value.splice(i, 1);
    }
  }

  function save() {
    persist({ tabs: tabs.value, activeKey: activeKey.value });
  }

  return {
    tabs, activeKey, recentlyClosed, cachedKeys,
    open, close, activate, reopenLast, markDirty, markClean,
    closeOthers, closeRight, closeAll, togglePin, reorder,
  };
});
