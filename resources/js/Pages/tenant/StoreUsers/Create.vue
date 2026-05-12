<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import TenantLayout from '@/layouts/TenantLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { ElForm, ElFormItem, ElSelect, ElOption, ElButton, ElCard } from 'element-plus';

defineOptions({ layout: TenantLayout });

const props = defineProps<{
  store: { id: string; name: string };
  candidates: Array<{ id: string; name: string; phone: string }>;
  storeRoles: Array<{ id: string; name: string; is_system: boolean }>;
}>();

const form = useForm({ user_id: '', role_id: '' });

function submit() { form.post(`/tenant/stores/${props.store.id}/users`); }
</script>

<template>
  <Head :title="`${props.store.name} · 加入人员`" />
  <PageHeader :breadcrumb="[
    { label: '门店列表', to: '/tenant/stores' },
    { label: props.store.name, to: `/tenant/stores/${props.store.id}/users` },
    { label: '加入人员' },
  ]">
    <template #actions>
      <ElButton @click="router.visit(`/tenant/stores/${props.store.id}/users`)">取消</ElButton>
      <ElButton type="primary" :loading="form.processing" @click="submit">加入</ElButton>
    </template>
  </PageHeader>
  <ElCard shadow="never" class="mt-3 max-w-xl">
    <ElForm label-position="top">
      <ElFormItem label="人员" :error="form.errors.user_id">
        <ElSelect v-model="form.user_id" filterable placeholder="选择本租户成员" style="width: 100%">
          <ElOption v-for="c in props.candidates" :key="c.id" :value="c.id" :label="`${c.name} · ${c.phone}`" />
        </ElSelect>
      </ElFormItem>
      <ElFormItem label="门店角色" :error="form.errors.role_id">
        <ElSelect v-model="form.role_id" placeholder="选择角色" style="width: 100%">
          <ElOption v-for="r in props.storeRoles" :key="r.id" :value="r.id"
                    :label="r.is_system ? `${r.name}（系统）` : r.name" />
        </ElSelect>
      </ElFormItem>
    </ElForm>
  </ElCard>
</template>
