<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { ElCard } from 'element-plus';

// 租户后台仪表盘占位页：UI-1 阶段会替换为今日营业 / 库存预警等数据卡片。
defineOptions({ layout: TenantLayout });

const shortcuts = [
  { href: '/tenant/items', title: '商品管理', desc: '维护本租户的商品 SPU 与价格、上下架' },
  { href: '/tenant/categories', title: '商品分类', desc: '维护商品分类（平列）' },
  { href: '/tenant/stock', title: '库存查询', desc: '按门店查看 SKU 库存与流水' },
  { href: '/tenant/users', title: '用户管理', desc: '邀请成员、维护租户级角色' },
];
</script>

<template>
  <Head title="仪表盘" />
  <div class="text-[12.5px] mt-3" style="color: var(--text-muted)">
    骨架占位，后续替换为今日营业 / 库存预警等数据卡片。
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
