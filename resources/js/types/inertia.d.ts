import '@inertiajs/core';

declare module '@inertiajs/core' {
  interface PageProps {
    auth: {
      user: {
        id: string;
        name: string;
        email: string;
        is_platform_admin: boolean;
      } | null;
    };
    tenant: {
      current: { id: string; name: string } | null;
      available: Array<{ id: string; name: string }>;
    };
    permissions: string[];
    flash: {
      success?: string;
      error?: string;
      info?: string;
    };
    app: {
      name: string;
      env: 'production' | 'staging' | 'local';
      /** API 健康度 mock；仅总后台 HealthPill 消费 */
      health: {
        uptime: string;   // '99.97%'
        p95: string;      // '142ms'
        tone: 'ok' | 'warn' | 'danger';
      };
    };
    /** 通知中心 mock；租户/总后台共用 */
    notifications: {
      unread: number;
      recent: Array<{
        tone: 'ok' | 'warn' | 'danger' | 'info';
        label: string;     // 分类名（'库存预警' / 'P0' 等）
        body: string;      // 通知正文，可含 mono 文本
        time: string;      // '2 分钟前'
        url?: string;      // 点击跳转
      }>;
    };
  }
}

// 扩展 Vue ComponentCustomOptions 让 defineOptions({ layout }) 在 TS 下合法。
// Inertia v2 通过 page 组件的静态 layout 属性自动应用 layout，无需手动包装。
declare module 'vue' {
  interface ComponentCustomOptions {
    layout?: unknown;
  }
}
