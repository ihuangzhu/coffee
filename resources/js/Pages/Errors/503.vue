<script setup lang="ts">
/**
 * 503 错误页 - 服务维护中。
 * 由 bootstrap/app.php withExceptions 在非 local/testing 环境捕获 503 响应渲染。
 */
import GuestLayout from '@/layouts/GuestLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineOptions({ layout: GuestLayout });
</script>

<template>
  <Head title="503 - 服务维护中" />
  <div class="text-center bg-white rounded-lg p-12 shadow-[var(--shadow-overlay)]">
    <div class="text-6xl font-mono-num text-[var(--color-primary)]">503</div>
    <p class="mt-4 text-lg">服务维护中，请稍后再试</p>
    <Link href="/" class="inline-block mt-6 text-[var(--color-primary)] hover:underline">返回首页</Link>
  </div>
</template>
