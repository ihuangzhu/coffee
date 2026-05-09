<script setup lang="ts">
/**
 * NotificationCenter：TopBar 铃铛 + popover 通知中心。
 * - 数据源：Inertia SharedProps.notifications（mock，schema 与未来真实推送一致）
 * - 未读数显示在铃铛右上小红点（点击 popover 中"全部已读"会本地清零，不调后端）
 * - popover 内用 StatusPill 标识每条通知分类
 * - 租户后台 / 总后台共用，差异由数据源驱动（后续后端按 user 上下文返回）
 */
import { usePage } from '@inertiajs/vue3';
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import StatusPill from './StatusPill.vue';

const page = usePage();
const notifications = computed(() => page.props.notifications);

const open = ref(false);
const localRead = ref(false);
const unread = computed(() => (localRead.value ? 0 : notifications.value.unread));

function toggle() { open.value = !open.value; }
function markAllRead() { localRead.value = true; }

/** 点 popover 外部关闭：用 mousedown 而非 click，避免按钮自身触发后立即被外部 close */
const rootRef = ref<HTMLElement | null>(null);
function onDocMouseDown(e: MouseEvent) {
  if (!open.value) return;
  if (rootRef.value && !rootRef.value.contains(e.target as Node)) open.value = false;
}
onMounted(() => document.addEventListener('mousedown', onDocMouseDown));
onBeforeUnmount(() => document.removeEventListener('mousedown', onDocMouseDown));
</script>

<template>
  <div ref="rootRef" class="relative">
    <button class="relative w-9 h-9 grid place-items-center rounded-md hover:bg-[var(--slate-100)]"
            @click="toggle">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor"
           stroke-width="2" style="color: var(--text-muted)">
        <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
        <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
      </svg>
      <span v-if="unread > 0" class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full"
            :style="{ background: 'var(--danger)' }" />
    </button>

    <div v-if="open"
         class="absolute top-12 right-0 w-[380px] rounded-[10px] z-40 overflow-hidden"
         style="background: #FFFFFF; border: 1px solid var(--slate-200); box-shadow: var(--shadow-lg);">
      <div class="px-4 py-3 border-b flex items-center justify-between"
           style="border-color: var(--border)">
        <span class="text-[14px] font-semibold" style="color: var(--text)">通知</span>
        <button class="text-[12px]" style="color: var(--primary)" @click="markAllRead">
          全部已读
        </button>
      </div>
      <div v-if="notifications.recent.length">
        <div v-for="(n, i) in notifications.recent" :key="i"
             class="px-4 py-3 cursor-pointer hover:bg-[var(--slate-50)]"
             :style="i > 0 ? 'border-top: 1px solid var(--slate-100)' : ''">
          <div class="flex items-center gap-2 text-[12px]" style="color: var(--text-muted)">
            <StatusPill :tone="n.tone" :label="n.label" />
            <span class="ml-auto">{{ n.time }}</span>
          </div>
          <div class="mt-1.5 text-[13.5px]" style="color: var(--text)">{{ n.body }}</div>
        </div>
      </div>
      <div v-else class="px-4 py-8 text-[13px] text-center" style="color: var(--text-faint)">
        暂无通知
      </div>
    </div>
  </div>
</template>
