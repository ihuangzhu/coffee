<script setup lang="ts">
/**
 * EnvBadge：仅在非 production 环境显示的环境徽章。
 * 用在总后台 PlatformLayout TopBar 品牌字旁，避免误操作生产。
 * 租户后台不显示（B2C 场景不需要平台运维提示）。
 */
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const env = computed(() => page.props.app.env);
const visible = computed(() => env.value !== 'production');
</script>

<template>
  <span v-if="visible"
        class="font-mono text-[10.5px] font-medium uppercase tracking-[.06em] px-2 py-[3px] rounded"
        style="background: var(--warn-soft); color: var(--warn-text); border: 1px solid #FED7AA;">
    {{ env }}
  </span>
</template>
