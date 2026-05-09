import { onMounted, onBeforeUnmount } from 'vue';

/**
 * Shortcut：单个快捷键定义。
 * - combo: 'mod+k' / 'mod+shift+t' / 序列 'g d'（空格分隔，仅支持双键序列）
 *   mod 在 Mac 为 Cmd，其他平台为 Ctrl
 * - scope: 仅作分类与去重 key 用，UI-0 暂不区分作用域生效
 * - description: 帮助弹窗中展示的人类可读说明
 * - handler: 触发回调；上层默认 preventDefault
 */
export interface Shortcut {
  combo: string;
  scope: 'global' | 'list' | 'form';
  description: string;
  handler: (e: KeyboardEvent) => void;
}

/** 单例 registry：scope+combo 唯一，重复注册后者覆盖前者 */
const registry = new Map<string, Shortcut>();

export function registerShortcut(s: Shortcut) {
  registry.set(`${s.scope}:${s.combo}`, s);
}

export function unregisterShortcut(scope: string, combo: string) {
  registry.delete(`${scope}:${combo}`);
}

export function listShortcuts(): Shortcut[] {
  return Array.from(registry.values());
}

/** 序列缓冲：800ms 内的连续单键累积成 'a b c' 形式 */
let sequenceBuffer: string[] = [];
let sequenceTimer: number | null = null;

/**
 * 注册全局 keydown 监听。app.ts 启动时调用一次。
 * 输入框/文本域/可编辑元素内默认放行（让浏览器原生处理），
 * 但保留 mod 修饰键（mod+k / mod+s）与 Esc，使命令面板等仍可在 input 内触发。
 */
export function installShortcuts() {
  document.addEventListener('keydown', (e) => {
    const target = e.target as HTMLElement;
    if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable)) {
      if (!(e.ctrlKey || e.metaKey || e.key === 'Escape')) return;
    }

    const mod = e.metaKey || e.ctrlKey ? 'mod+' : '';
    const shift = e.shiftKey ? 'shift+' : '';
    const key = e.key.toLowerCase();
    const combo = `${mod}${shift}${key}`;

    // 单键序列（g d / g u）：仅在无修饰键、纯字母时才进入序列模式
    if (!mod && !e.shiftKey && /^[a-z]$/.test(key)) {
      sequenceBuffer.push(key);
      if (sequenceTimer) clearTimeout(sequenceTimer);
      sequenceTimer = window.setTimeout(() => { sequenceBuffer = []; }, 800);
      const seq = sequenceBuffer.join(' ');
      if (tryFire(seq, e)) sequenceBuffer = [];
      return;
    }

    sequenceBuffer = [];
    tryFire(combo, e);
  });
}

/** 在 registry 中找匹配 combo，命中即 preventDefault + 调用 handler */
function tryFire(combo: string, e: KeyboardEvent): boolean {
  for (const s of registry.values()) {
    if (s.combo === combo) {
      e.preventDefault();
      s.handler(e);
      return true;
    }
  }
  return false;
}

/**
 * 组件级 hook：mounted 时注册一组 shortcut，unmount 时反注册。
 * 用于页面/对话框内临时快捷键（如表单内 mod+s 保存）。
 */
export function useShortcuts(shortcuts: Shortcut[]) {
  onMounted(() => shortcuts.forEach(registerShortcut));
  onBeforeUnmount(() => shortcuts.forEach((s) => unregisterShortcut(s.scope, s.combo)));
}
