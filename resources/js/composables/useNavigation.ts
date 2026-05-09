/**
 * 导航数据源：租户后台 / 总后台两套一级模块定义。
 * 仅描述菜单结构，不涉及权限运行时判定（由 useMenu / usePermissions 在渲染期过滤）。
 * 后续 UI-1..4 各业务模块按 key 追加自己的 sidebar 子树即可。
 */

/**
 * 二级（及以下）菜单项。
 * - key: 唯一稳定标识，用于 v-for / 状态持久化
 * - label: i18n key（如 nav.users），渲染前由 useI18n().t 翻译
 * - url: Inertia 跳转路径；缺省时表示折叠分组节点
 * - permission: 字符串权限码，缺省视为公开；运行时由 hasPermission 与 SharedProps.permissions 比较
 * - children: 多级子菜单，结构同 MenuItem
 */
export interface MenuItem {
  key: string;
  label: string;
  icon?: string;
  routeName?: string;
  url?: string;
  permission?: string;
  children?: MenuItem[];
}

/**
 * 一级模块定义。
 * - defaultUrl: 用户点击 TopNav 的入口跳转地址
 * - permission: 缺省即公开；如设置则模块整体隐藏（含 sidebar）
 * - sidebar: 该模块下二级菜单树
 */
export interface ModuleNav {
  key: string;
  label: string;
  defaultUrl: string;
  permission?: string;
  sidebar: MenuItem[];
}

/** 租户后台一级模块定义。后续 UI-1..4 模块按 key 追加自己的 sidebar 子树。 */
export const tenantModules: ModuleNav[] = [
  {
    key: 'dashboard',
    label: 'nav.dashboard',
    defaultUrl: '/tenant/dashboard',
    sidebar: [
      { key: 'overview', icon: 'PieChart', label: 'nav.overview', url: '/tenant/dashboard' },
    ],
  },
  {
    key: 'settings',
    label: 'nav.settings',
    defaultUrl: '/tenant/users',
    sidebar: [
      { key: 'profile', icon: 'Avatar', label: 'nav.profile', url: '/tenant/profile' },
      { key: 'users', icon: 'UserFilled', label: 'nav.users', url: '/tenant/users', permission: 'users.read' },
      { key: 'roles', icon: 'Key', label: 'nav.roles', url: '/tenant/roles', permission: 'roles.read' },
      // 租户后台只读门店列表 → 行内"成员"跳 stores/{id}/users
      { key: 'stores', icon: 'Shop', label: 'nav.stores', url: '/tenant/stores', permission: 'stores.read' },
    ],
  },
];

/** 总后台一级模块定义。 */
export const platformModules: ModuleNav[] = [
  {
    key: 'tenants',
    label: 'nav.platform.tenants',
    defaultUrl: '/platform/tenants',
    sidebar: [
      { key: 'tenants', icon: 'OfficeBuilding', label: 'nav.platform.tenant_list', url: '/platform/tenants' },
    ],
  },
];

/**
 * 根据当前路径定位所属一级模块。
 * 匹配优先级：完全相等 defaultUrl > 路径前缀 defaultUrl > 任意 sidebar.url 前缀命中。
 * 全部不命中时回落到第一个可见模块（modules[0]），用于例如 /tenant/foo-unknown 仍有高亮 tab。
 */
export function moduleByUrl(modules: ModuleNav[], pathname: string): ModuleNav | null {
  return modules.find((m) =>
    pathname === m.defaultUrl ||
    pathname.startsWith(m.defaultUrl) ||
    m.sidebar.some((s) => s.url && pathname.startsWith(s.url)),
  ) ?? modules[0] ?? null;
}
