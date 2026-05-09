<script setup lang="ts">
/**
 * HealthPill：API 健康度展示。
 * - 数据源：Inertia SharedProps.app.health（mock 字段，由 HandleInertiaRequests 提供）
 * - tone 决定 dot 颜色与背景色族（ok/warn/danger）
 * - 仅总后台 TopBar 使用；租户后台不展示平台健康度
 */
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const health = computed(() => page.props.app.health);

const TONE_STYLE: Record<'ok' | 'warn' | 'danger', { bg: string; fg: string; border: string; dot: string }> = {
  ok:     { bg: 'var(--ok-soft)',     fg: 'var(--ok-text)',     border: '#A7F3D0', dot: 'var(--ok)' },
  warn:   { bg: 'var(--warn-soft)',   fg: 'var(--warn-text)',   border: '#FED7AA', dot: 'var(--warn)' },
  danger: { bg: 'var(--danger-soft)', fg: 'var(--danger-text)', border: '#FECACA', dot: 'var(--danger)' },
};

const style = computed(() => TONE_STYLE[health.value.tone]);
</script>

<template>
  <div class="flex items-center gap-2 h-8 px-3 rounded-md font-mono text-[11.5px]"
       :style="{ background: style.bg, color: style.fg, border: `1px solid ${style.border}` }">
    <span class="w-1.5 h-1.5 rounded-full inline-block" :style="{ background: style.dot }" />
    api {{ health.uptime }} · p95 {{ health.p95 }}
  </div>
</template>
