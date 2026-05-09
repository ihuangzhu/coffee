import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { tenantModules, platformModules, moduleByUrl, type ModuleNav, type MenuItem } from './useNavigation';
import { hasPermission } from './usePermissions';

/**
 * 根据 scope 选取模块定义并按当前用户权限过滤可见菜单。
 * - visibleModules: 按 module.permission 过滤后的一级模块列表（用于 TopNav）
 * - currentModule: 由 window.location.pathname 解析得到当前一级模块（用于 sidebar 切换）
 * - sidebarMenu: 当前模块下、按 item.permission 递归过滤的二级菜单（用于 Sidebar 渲染）
 *
 * 注意：currentModule 依赖 window.location.pathname，Inertia SPA navigate 后 pathname 同步更新，
 * 但 computed 不会自动 re-evaluate（无响应式依赖）。组件需在 router.on('navigate') 后强制刷新或
 * 通过 usePage().url 触发依赖（这里 page.props 变化即触发）。
 */
export function useMenu(scope: 'tenant' | 'platform') {
  const page = usePage();
  const modules = scope === 'tenant' ? tenantModules : platformModules;

  const visibleModules = computed<ModuleNav[]>(() =>
    modules.filter((m) => !m.permission || hasPermission(page, m.permission)),
  );

  const currentModule = computed<ModuleNav | null>(() => {
    // 读取 page.url 让 computed 在 Inertia 路由变化时重算（page.url 是响应式的）
    void page.url;
    return moduleByUrl(visibleModules.value, window.location.pathname);
  });

  /**
   * 递归按权限过滤菜单项；保留无权限项的 children 也一并清理。
   */
  function filterMenu(items: MenuItem[]): MenuItem[] {
    return items
      .filter((i) => !i.permission || hasPermission(page, i.permission))
      .map((i) => ({ ...i, children: i.children ? filterMenu(i.children) : undefined }));
  }

  const sidebarMenu = computed<MenuItem[]>(() =>
    currentModule.value ? filterMenu(currentModule.value.sidebar) : [],
  );

  return { modules: visibleModules, currentModule, sidebarMenu };
}
