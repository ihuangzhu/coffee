<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import TenantLayout from '@/layouts/TenantLayout.vue';

// 租户后台仪表盘占位页：UI-1 阶段会替换为今日营业 / 库存预警等数据卡片。
// 使用 TenantLayout 提供 TopBar / Sidebar / 主内容区 shell。
defineOptions({ layout: TenantLayout });
</script>

<template>
  <Head title="仪表盘" />
  <h1 class="text-xl font-semibold">仪表盘</h1>
  <p class="text-gray-500 mt-2">骨架占位 - 后续 UI-1 替换为实际数据卡片</p>

  <!-- 快捷入口：UI-1 阶段补充正式卡片前的临时导航入口 -->
  <div class="mt-6 flex flex-wrap gap-4">
    <!-- 商品管理入口 -->
    <Link
      href="/tenant/goods"
      class="block rounded-lg border border-gray-200 p-4 w-56 hover:border-blue-400 hover:shadow-sm transition"
    >
      <div class="font-medium text-gray-800">商品管理</div>
      <div class="text-sm text-gray-500 mt-1">维护本租户的商品 SPU 与价格、上下架</div>
    </Link>

    <!-- 商品分类入口 -->
    <Link
      href="/tenant/categories"
      class="block rounded-lg border border-gray-200 p-4 w-56 hover:border-blue-400 hover:shadow-sm transition"
    >
      <div class="font-medium text-gray-800">商品分类</div>
      <div class="text-sm text-gray-500 mt-1">维护商品分类（平列）</div>
    </Link>
  </div>
</template>
