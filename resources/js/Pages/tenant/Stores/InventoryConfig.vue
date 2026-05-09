<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

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

function submit() {
  router.patch(`/tenant/stores/${props.value.store.id}/inventory`, form.value);
}
</script>

<template>
  <div class="p-6 max-w-3xl">
    <h1 class="text-xl font-medium mb-4">{{ props.store.name }} - 库存配置</h1>

    <form @submit.prevent="submit" class="space-y-3 text-sm">
      <div class="flex items-center justify-between border-b py-2">
        <span>启用库存（覆盖租户开关）</span>
        <input type="checkbox" v-model="form.inventory_enabled" />
      </div>
      <div class="flex items-center justify-between border-b py-2">
        <span>启用多货架</span>
        <input type="checkbox" v-model="form.multi_location_enabled" />
      </div>
      <div class="flex items-center justify-between border-b py-2">
        <span>默认库存模式</span>
        <input v-model="form.default_stock_mode"
          class="px-2 py-1 border rounded w-40" />
      </div>
      <div class="flex items-center justify-between border-b py-2">
        <span>启用自制/生产</span>
        <input type="checkbox" v-model="form.production_enabled" />
      </div>
      <div class="flex items-center justify-between border-b py-2">
        <span>允许直接库存调整</span>
        <input type="checkbox" v-model="form.allow_direct_stock_adjustment" />
      </div>

      <button type="submit"
        class="mt-4 px-4 py-2 bg-indigo-600 text-white rounded">保存</button>
    </form>
  </div>
</template>
