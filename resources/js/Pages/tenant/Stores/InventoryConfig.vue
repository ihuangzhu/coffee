<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
  ElCard, ElForm, ElFormItem, ElInput, ElSwitch, ElButton,
} from 'element-plus';
import TenantLayout from '@/layouts/TenantLayout.vue';
import PageHeader from '@/components/PageHeader.vue';

defineOptions({ layout: TenantLayout });

interface Cfg {
  inventory_enabled: boolean; multi_location_enabled: boolean;
  default_stock_mode: string; production_enabled: boolean;
  allow_direct_stock_adjustment: boolean;
}

const page = usePage();
const props = computed(() => page.props as unknown as {
  store: { id: string; name: string };
  config: Cfg;
});
const form = ref<Cfg>({ ...props.value.config });
const processing = ref(false);

function submit() {
  processing.value = true;
  router.patch(`/tenant/stores/${props.value.store.id}/inventory`, form.value, {
    onFinish: () => { processing.value = false; },
  });
}
</script>

<template>
  <Head :title="`${props.store.name} · 库存配置`" />
  <PageHeader :breadcrumb="[
    { label: '门店列表', to: '/tenant/stores' },
    { label: `${props.store.name} 库存配置` },
  ]">
    <template #actions>
      <ElButton type="primary" :loading="processing" @click="submit">保存</ElButton>
    </template>
  </PageHeader>

  <ElCard shadow="never" class="mt-3 max-w-3xl">
    <ElForm label-position="left" label-width="200px">
      <ElFormItem>
        <template #label>
          <div class="leading-tight">
            <div class="text-[13px]">启用库存</div>
            <div class="text-[12px]" style="color: var(--text-muted)">覆盖租户级开关</div>
          </div>
        </template>
        <ElSwitch v-model="form.inventory_enabled" />
      </ElFormItem>
      <ElFormItem label="启用多货架">
        <ElSwitch v-model="form.multi_location_enabled" />
      </ElFormItem>
      <ElFormItem label="默认库存模式">
        <ElInput v-model="form.default_stock_mode" style="width: 240px" />
      </ElFormItem>
      <ElFormItem label="启用自制 / 生产">
        <ElSwitch v-model="form.production_enabled" />
      </ElFormItem>
      <ElFormItem label="允许直接库存调整">
        <ElSwitch v-model="form.allow_direct_stock_adjustment" />
      </ElFormItem>
    </ElForm>
  </ElCard>
</template>
