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
      if (!url.startsWith('/tenant/') || url === '/tenant/login' || url === '/tenant/select') return;
      const tabs = useTabsStore();
      const isDashboard = url === '/tenant/dashboard';
      tabs.open({ key: url, title: deriveTitleFromUrl(url), pinned: isDashboard });
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
  if (url === '/tenant/dashboard') return '仪表盘';
  if (url === '/tenant/profile') return '个人资料';
  if (url.startsWith('/tenant/users/create')) return '新建用户';
  if (url.startsWith('/tenant/users/') && url.endsWith('/edit')) return '编辑用户';
  if (url.startsWith('/tenant/users')) return '用户管理';
  return url;
}
