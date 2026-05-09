<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, watch } from 'vue';
import { ElMessage } from 'element-plus';

/**
 * GuestLayout：未登录场景（登录、选租户）的最外层 layout。
 * 提供：mesh 渐变背景 + 居中卡片容器 + Inertia flash 转 ElMessage 提示。
 */
const page = usePage();
const flash = computed(() => page.props.flash);

/**
 * 将 Inertia 共享 props 中的 flash 字段转成 ElMessage toast 提示。
 * 仅在挂载时与 flash 变化时调用，避免重复弹出。
 */
function showFlash() {
  if (flash.value.success) ElMessage.success(flash.value.success);
  if (flash.value.error) ElMessage.error(flash.value.error);
  if (flash.value.info) ElMessage.info(flash.value.info);
}

onMounted(showFlash);
watch(flash, showFlash, { deep: true });
</script>

<template>
  <div class="guest-layout min-h-screen flex items-center justify-center relative overflow-hidden">
    <!-- mesh 渐变背景：通过两个 radial-gradient 叠加创造柔和的视觉层次 -->
    <div class="bg-mesh absolute inset-0 -z-10"></div>
    <slot />
  </div>
</template>

<style scoped>
.bg-mesh {
  background:
    radial-gradient(at 20% 30%, rgba(37, 99, 235, 0.15), transparent 50%),
    radial-gradient(at 80% 70%, rgba(139, 92, 246, 0.15), transparent 50%),
    var(--color-bg);
}
</style>
