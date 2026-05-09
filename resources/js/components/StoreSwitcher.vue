<script setup lang="ts">
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { ElDropdown, ElDropdownMenu, ElDropdownItem } from 'element-plus';

/**
 * StoreSwitcher：TopBar 门店切换下拉。
 * - 单门店时仅展示门店名（不出下拉）
 * - 多门店时点击展开 user 在当前 tenant 下可访问的门店
 * - 选定后 POST /tenant/store/switch 写入 session.current_store_id 并整页刷新
 *
 * 数据来源：page.props.store.{current, available}（由 ResolveCurrentStore 注入）
 */

interface StoreItem { id: string; name: string }

const page = usePage();
const stores = computed<StoreItem[]>(() => (page.props.store as any)?.available ?? []);
const current = computed<StoreItem | null>(() => (page.props.store as any)?.current ?? null);

/** 切换到指定门店；若与当前一致则忽略，避免无意义请求 */
function switchTo(id: string) {
  if (id === current.value?.id) return;
  router.post('/tenant/store/switch', { store_id: id }, { preserveScroll: true });
}
</script>

<template>
  <div v-if="stores.length" class="flex items-center">
    <span v-if="stores.length === 1" class="text-[13px]" :style="{ color: 'var(--text-mid)' }">
      {{ current?.name ?? stores[0].name }}
    </span>
    <ElDropdown v-else trigger="click" @command="switchTo">
      <button class="flex items-center gap-1.5 h-8 px-2.5 rounded-md hover:bg-[var(--slate-100)] text-[13px]"
              :style="{ color: 'var(--text)' }">
        <span>{{ current?.name ?? '选择门店' }}</span>
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="6 9 12 15 18 9" />
        </svg>
      </button>
      <template #dropdown>
        <ElDropdownMenu>
          <ElDropdownItem v-for="s in stores" :key="s.id" :command="s.id">
            {{ s.name }}
          </ElDropdownItem>
        </ElDropdownMenu>
      </template>
    </ElDropdown>
  </div>
</template>
