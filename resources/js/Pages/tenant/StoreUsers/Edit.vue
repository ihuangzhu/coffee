<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import TenantLayout from '@/layouts/TenantLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { ElForm, ElFormItem, ElSelect, ElOption, ElButton } from 'element-plus';

defineOptions({ layout: TenantLayout });

const props = defineProps<{
  store: { id: string; name: string };
  binding: { user_id: string; name: string; phone: string; role_id: string };
  storeRoles: Array<{ id: string; name: string; is_system: boolean }>;
}>();

const form = useForm({ role_id: props.binding.role_id });

function submit() { form.patch(`/tenant/stores/${props.store.id}/users/${props.binding.user_id}`); }
</script>

<template>
  <Head :title="`${props.store.name} · ${props.binding.name}`" />
  <PageHeader :title="`${props.store.name} · ${props.binding.name}（${props.binding.phone}）`" />
  <div class="bg-white rounded-md p-4 shadow-[var(--shadow-card)]">
    <ElForm @submit.prevent="submit">
      <ElFormItem label="门店角色" :error="form.errors.role_id">
        <ElSelect v-model="form.role_id" placeholder="选择角色" style="width: 360px">
          <ElOption v-for="r in props.storeRoles" :key="r.id" :value="r.id"
                    :label="r.is_system ? `${r.name}（系统）` : r.name" />
        </ElSelect>
      </ElFormItem>
      <ElFormItem>
        <ElButton type="primary" :loading="form.processing" native-type="submit">保存</ElButton>
      </ElFormItem>
    </ElForm>
  </div>
</template>
