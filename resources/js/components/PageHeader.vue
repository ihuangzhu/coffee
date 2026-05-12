<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

/**
 * PageHeader：标准业务页页头骨架（单行）。
 * - 不渲染 page title（与 tab 重复），保留 title prop 兼容旧调用
 * - breadcrumb（可选）：左端层级链，详情页用
 * - filter slot：紧跟 breadcrumb 后；列表页筛选条
 * - actions slot：右端按钮组
 *
 * 全部组件 sticky 在 layout 顶部一行展示，不允许换行。
 */
defineProps<{
  title?: string;
  breadcrumb?: Array<{ label: string; to?: string }>;
}>();
</script>

<template>
  <div class="page-header sticky top-0 z-[5] -mx-6 px-6 py-2.5 border-b flex items-center gap-3"
       style="background: var(--surface); border-color: var(--border);">
    <nav v-if="breadcrumb?.length" class="flex items-center gap-1.5 text-[12.5px]"
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

    <div v-if="$slots.filter" class="flex items-center gap-2">
      <slot name="filter" />
    </div>

    <div class="flex-1" />

    <div v-if="$slots.actions" class="flex items-center gap-2">
      <slot name="actions" />
    </div>
  </div>
</template>
