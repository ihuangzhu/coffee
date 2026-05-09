import { defineStore } from 'pinia';
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { tenantModules } from '@/composables/useNavigation';
import type { MenuItem } from '@/composables/useNavigation';
import { useTabsStore } from './tabs';

/**
 * Command：命令面板单条候选项。
 * - id: 唯一标识，用于 v-for + 后续可能的快捷指令引用
 * - label: 展示文本（可能是嵌套 i18n key 用 ' > ' 拼接，渲染时再翻译每段）
 * - hint: 右侧灰色提示（如最近访问的 URL）
 * - group: 决定渲染端的色块标签（命令/最近/页面）
 * - handler: 选中后执行；通常调用 router.visit 或 store action
 */
export interface Command {
  id: string;
  label: string;
  hint?: string;
  group: 'command' | 'page' | 'recent';
  handler: () => void;
}

/**
 * CommandsStore：命令面板状态 + 候选项构建。
 * 候选三类拼接：自定义命令（注册时机静态） + 最近访问（来自 TabsStore） + 页面（菜单展平）。
 * 每次 buildAll 实时计算，无需缓存（菜单结构静态，tabs 列表不大）。
 */
export const useCommandsStore = defineStore('commands', () => {
  const visible = ref(false);
  const customCommands = ref<Command[]>([]);

  function open() { visible.value = true; }
  function close() { visible.value = false; }
  function register(cmd: Command) { customCommands.value.push(cmd); }

  /**
   * 拼装全部候选项。
   * - pages: 递归遍历 tenantModules.sidebar，每个有 url 的叶节点生成一条 page 命令
   *   label 形如 'nav.settings > nav.users'，渲染时按 ' > ' 切分逐段 i18n.t 翻译
   * - recent: 取 tabs 末 5 个（最近活跃在末尾），label 用 tab.title
   * - 顺序：custom -> recent -> pages，保证用户自定义命令置顶
   */
  function buildAll(): Command[] {
    const tabs = useTabsStore();

    const pages: Command[] = [];
    function walk(items: MenuItem[], crumb: string[] = []) {
      for (const i of items) {
        const trail = [...crumb, i.label];
        if (i.url) {
          pages.push({
            id: 'page:' + i.url,
            label: trail.join(' > '),
            group: 'page',
            handler: () => router.visit(i.url!),
          });
        }
        if (i.children) walk(i.children, trail);
      }
    }
    for (const m of tenantModules) {
      walk(m.sidebar, [m.label]);
    }

    const recent: Command[] = tabs.tabs.slice(-5).map((t) => ({
      id: 'recent:' + t.key,
      label: t.title,
      hint: t.key,
      group: 'recent',
      handler: () => router.visit(t.key),
    }));

    return [...customCommands.value, ...recent, ...pages];
  }

  return { visible, customCommands, open, close, register, buildAll };
});
