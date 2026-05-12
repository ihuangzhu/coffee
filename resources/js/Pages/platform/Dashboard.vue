<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PlatformLayout from '@/layouts/PlatformLayout.vue';
import { ElCard } from 'element-plus';

// 总后台仪表盘占位页：UI-1 阶段会替换为平台运营数据卡片。
defineOptions({ layout: PlatformLayout });

const shortcuts = [
  { href: '/platform/tenants', title: '租户管理', desc: '查看 / 启停 / 进入任意租户后台' },
];
</script>

<template>
  <Head title="总后台" />
  <div class="text-[12.5px] mt-3" style="color: var(--text-muted)">
    骨架占位，后续替换为平台运营数据卡片。
  </div>
  <div class="mt-3 grid grid-cols-2 lg:grid-cols-4 gap-3">
    <Link v-for="s in shortcuts" :key="s.href" :href="s.href">
      <ElCard shadow="hover" class="cursor-pointer h-full">
        <div class="text-[14px] font-semibold">{{ s.title }}</div>
        <div class="text-[12.5px] mt-1.5" style="color: var(--text-muted)">{{ s.desc }}</div>
      </ElCard>
    </Link>
  </div>
</template>
