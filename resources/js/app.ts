import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import ElementPlus from 'element-plus';
import zhCn from 'element-plus/es/locale/lang/zh-cn';
import 'element-plus/dist/index.css';
import { createPinia } from 'pinia';
import i18n from '@/lib/i18n';
import { useTabsStore } from '@/stores/tabs';
import { useNavStore } from '@/stores/nav';
import { useCommandsStore } from '@/stores/commands';
import { installShortcuts, registerShortcut } from '@/composables/useShortcuts';
import './lib/axios';

createInertiaApp({
  resolve: (name) => {
    const pages = import.meta.glob('./pages/**/*.vue', { eager: false });
    const page = pages[`./pages/${name}.vue`];
    if (!page) throw new Error(`Inertia page not found: ${name}`);
    return (page as () => Promise<{ default: DefineComponent }>)().then((m) => m.default);
  },
  setup({ el, App, props, plugin }) {
    const app = createApp({ render: () => h(App, props) });
    app.use(plugin);
    const pinia = createPinia();
    app.use(pinia);
    app.use(ElementPlus, { locale: zhCn });
    app.use(i18n);

    router.on('navigate', (event) => {
      const url = event.detail.page.url;
      const isTenant = url.startsWith('/tenant/');
      const isPlatform = url.startsWith('/platform/');
      if (!isTenant && !isPlatform) return;
      // 排除登录 / 选租户 / 登录回跳类路径，这些不应该出现在 tab
      if (url === '/tenant/login' || url === '/tenant/select' || url === '/platform/login') return;
      const tabs = useTabsStore();
      const isHome = url === '/tenant/dashboard' || url === '/platform/dashboard';
      tabs.open({ key: url, title: deriveTitleFromUrl(url), pinned: isHome });
    });

    installShortcuts();
    registerShortcut({
      combo: 'mod+b', scope: 'global', description: '切换侧栏折叠',
      handler: () => useNavStore().toggleSidebar(),
    });
    registerShortcut({
      combo: '[', scope: 'global', description: '折叠 / 展开侧栏（单键）',
      handler: () => useNavStore().toggleSidebar(),
    });
    registerShortcut({
      combo: 'mod+w', scope: 'global', description: '关闭当前 Tab',
      handler: () => {
        const tabs = useTabsStore();
        if (tabs.activeKey) tabs.close(tabs.activeKey);
      },
    });
    registerShortcut({
      combo: 'mod+shift+t', scope: 'global', description: '重开最近关闭 Tab',
      handler: () => {
        const url = useTabsStore().reopenLast();
        if (url) router.visit(url);
      },
    });
    registerShortcut({
      combo: 'g d', scope: 'global', description: '跳转仪表盘',
      handler: () => router.visit('/tenant/dashboard'),
    });
    registerShortcut({
      combo: 'g u', scope: 'global', description: '跳转用户管理',
      handler: () => router.visit('/tenant/users'),
    });
    registerShortcut({
      combo: 'mod+/', scope: 'global', description: '快捷键帮助',
      handler: () => window.dispatchEvent(new CustomEvent('ui0:shortcuts-help')),
    });
    registerShortcut({
      combo: 'mod+k', scope: 'global', description: '打开命令面板',
      handler: () => useCommandsStore().open(),
    });

    const cmds = useCommandsStore();
    cmds.register({
      id: 'cmd:logout', label: '退出登录', group: 'command',
      handler: () => router.post('/tenant/logout'),
    });
    cmds.register({
      id: 'cmd:toggle-sidebar', label: '折叠侧栏', group: 'command',
      handler: () => useNavStore().toggleSidebar(),
    });

    app.mount(el);
  },
  // delay=0 让进度条立即出现（默认 250ms 延迟会让快速请求"看不见"）；
  // showSpinner=true 在右上角额外提供一个圆形 spinner，作为顶部 bar 的补充反馈
  progress: { color: '#4F46E5', includeCSS: true, showSpinner: true, delay: 0 },
});

function deriveTitleFromUrl(url: string): string {
  // 平台后台
  if (url === '/platform/dashboard') return '总后台';
  if (url.startsWith('/platform/tenants/create')) return '新建租户';
  if (url.match(/^\/platform\/tenants\/[^/]+\/stores\/create/)) return '新建门店';
  if (url.match(/^\/platform\/tenants\/[^/]+\/stores\/[^/]+\/edit/)) return '编辑门店';
  if (url.match(/^\/platform\/tenants\/[^/]+\/stores/)) return '门店管理';
  if (url.match(/^\/platform\/tenants\/[^/]+\/edit/)) return '编辑租户';
  if (url.startsWith('/platform/tenants')) return '租户管理';
  // 租户后台
  if (url === '/tenant/dashboard') return '仪表盘';
  if (url === '/tenant/profile') return '个人资料';
  if (url.startsWith('/tenant/users/create')) return '新建用户';
  if (url.startsWith('/tenant/users/') && url.endsWith('/edit')) return '编辑用户';
  if (url.startsWith('/tenant/users')) return '用户管理';
  if (url.startsWith('/tenant/roles/create')) return '新建角色';
  if (url.startsWith('/tenant/roles/') && url.endsWith('/edit')) return '编辑角色';
  if (url.startsWith('/tenant/roles')) return '角色管理';
  if (url.match(/^\/tenant\/stores\/[^/]+\/users\/create/)) return '新建门店成员';
  if (url.match(/^\/tenant\/stores\/[^/]+\/users\/[^/]+\/edit/)) return '编辑门店成员';
  if (url.match(/^\/tenant\/stores\/[^/]+\/users/)) return '门店成员';
  if (url.match(/^\/tenant\/stores\/[^/]+\/inventory/)) return '门店库存配置';
  if (url.startsWith('/tenant/stores')) return '门店列表';
  if (url.startsWith('/tenant/categories/create')) return '新建分类';
  if (url.startsWith('/tenant/categories/') && url.endsWith('/edit')) return '编辑分类';
  if (url.startsWith('/tenant/categories')) return '商品分类';
  if (url.startsWith('/tenant/items/create')) return '新建商品';
  if (url.startsWith('/tenant/items/') && url.endsWith('/edit')) return '编辑商品';
  if (url.startsWith('/tenant/items')) return '商品管理';
  if (url.startsWith('/tenant/stock/adjust')) return '库存调整';
  if (url.startsWith('/tenant/stock/stocktake')) return '盘点';
  if (url.startsWith('/tenant/stock/damage')) return '报损';
  if (url.startsWith('/tenant/stock/txns')) return '库存流水';
  if (url.startsWith('/tenant/stock')) return '库存';
  if (url.startsWith('/tenant/settings/inventory')) return '库存设置';
  return url;
}
