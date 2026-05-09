<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

/**
 * PageHeader：标准业务页页头骨架。
 * - breadcrumb（可选）：mono 字距，最末段不可点
 * - title（必）：24px 600 slate-900
 * - subtitle（可选）：13.5px slate-500
 * - actions slot：标题右侧按钮组（约定：左 ghost 右 primary）
 * - filter slot：副标下方独立行（segmented + 搜索 + 筛选）
 *
 * sticky 让滚动表格时标题保留可见。
 */
defineProps<{
  title: string;
  subtitle?: string;
  breadcrumb?: Array<{ label: string; to?: string }>;
}>();
</script>

<template>
  <div class="page-header sticky top-0 z-[5] -mx-6 px-8 pt-6 pb-5 border-b"
       style="background: var(--surface); border-color: var(--border);">
    <div class="flex items-end gap-6">
      <div>
        <nav v-if="breadcrumb?.length" class="flex items-center gap-1.5 text-[12.5px] mb-2.5"
             style="color: var(--text-muted)">
          <template v-for="(b, i) in breadcrumb" :key="i">
            <Link v-if="b.to && i < breadcrumb.length - 1" :href="b.to" class="hover:text-[var(--text)]">
              {{ b.label }}
            </Link>
            <span v-else :style="i === breadcrumb.length - 1 ? { color: 'var(--text)' } : null">
              {{ b.label }}
            </span>
            <span v-if="i < breadcrumb.length - 1" style="color: var(--text-faint)">/</span>
          </template>
        </nav>
        <h1 class="text-[24px] leading-none font-semibold tracking-tight"
            style="color: var(--text)">{{ title }}</h1>
        <p v-if="subtitle" class="mt-2 text-[13.5px]" style="color: var(--text-muted)">
          {{ subtitle }}
        </p>
      </div>
      <div class="flex-1" />
      <div class="flex items-center gap-2"><slot name="actions" /></div>
    </div>

    <div v-if="$slots.filter" class="mt-5 flex items-center gap-3"><slot name="filter" /></div>
  </div>
</template>
