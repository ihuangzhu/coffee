import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { createPinia } from 'pinia';
import ElementPlus from 'element-plus';
import 'element-plus/dist/index.css';
import './lib/axios';

createInertiaApp({
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  resolve: (name): any => {
    const pages = import.meta.glob('./Pages/**/*.vue', { eager: true }) as Record<string, any>;
    return pages[`./Pages/${name}.vue`];
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .use(createPinia())
      .use(ElementPlus)
      .mount(el);
  },
});
