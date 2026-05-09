<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PlatformLayout from '@/layouts/PlatformLayout.vue';

// 总后台仪表盘占位页：UI-1 阶段会替换为平台运营数据卡片。
// 使用 PlatformLayout 提供深色侧栏 + 环境徽章的总后台 shell。
defineOptions({ layout: PlatformLayout });
</script>

<template>
  <Head title="总后台" />
  <h1 class="text-xl font-semibold">总后台仪表盘</h1>
</template>
