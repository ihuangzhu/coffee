<script setup lang="ts">
/**
 * StatusPill：标准化状态徽章。
 * - 永远 dot + label 形式，便于色弱识别（默认 dot=true）
 * - 4 个 tone 走 spec §2.1 状态色族（emerald/amber/red/sky）
 * - tone='mid' 用作中性最终态（"已完成 / 已归档"），slate 灰阶
 */
type Tone = 'ok' | 'warn' | 'danger' | 'info' | 'mid';

const props = withDefaults(defineProps<{ tone: Tone; label: string; dot?: boolean }>(), {
  dot: true,
});

/** 各 tone 的背景 + 文字色映射 (§2.1 spec) */
const TONE_BG: Record<Tone, string> = {
  ok:     'var(--ok-soft)',
  warn:   'var(--warn-soft)',
  danger: 'var(--danger-soft)',
  info:   'var(--info-soft)',
  mid:    'var(--slate-100)',
};
const TONE_FG: Record<Tone, string> = {
  ok:     'var(--ok-text)',
  warn:   'var(--warn-text)',
  danger: 'var(--danger-text)',
  info:   'var(--info-text)',
  mid:    'var(--text-muted)',
};
const TONE_DOT: Record<Tone, string> = {
  ok:     'var(--ok)',
  warn:   'var(--warn)',
  danger: 'var(--danger)',
  info:   'var(--info)',
  mid:    'var(--text-faint)',
};
</script>

<template>
  <span
    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-medium"
    :style="{ background: TONE_BG[props.tone], color: TONE_FG[props.tone] }">
    <span v-if="dot" class="w-1.5 h-1.5 rounded-full inline-block"
          :style="{ background: TONE_DOT[props.tone] }" />
    {{ label }}
  </span>
</template>
