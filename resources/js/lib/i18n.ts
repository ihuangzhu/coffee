import { createI18n } from 'vue-i18n';
import zhCN from './locales/zh-CN';

/**
 * Vue I18n 实例。
 *
 * Phase H3：拆分按域的 locale 文件（common/auth/nav/validation/pages/*），
 * 通过 locales/zh-CN/index.ts 聚合后挂到 messages['zh-CN']。
 *
 * 配置：
 * - legacy: false 启用 Composition API（useI18n().t / 模板 $t）
 * - missingWarn / fallbackWarn: 关闭以避免开发期未翻译 key 的 console 噪音
 *   （命令面板等动态拼接 key 会大量触发 warn）
 */
export default createI18n({
  legacy: false,
  locale: 'zh-CN',
  fallbackLocale: 'zh-CN',
  missingWarn: false,
  fallbackWarn: false,
  messages: {
    'zh-CN': zhCN,
  },
});
