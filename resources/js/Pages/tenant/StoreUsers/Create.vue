<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import TenantLayout from '@/layouts/TenantLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { ElForm, ElFormItem, ElSelect, ElOption, ElButton } from 'element-plus';

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
  <PageHeader :title="`${props.store.name} · 加入人员`" />
  <div class="bg-white rounded-md p-4 shadow-[var(--shadow-card)]">
    <ElForm @submit.prevent="submit">
      <ElFormItem label="人员" :error="form.errors.user_id">
        <ElSelect v-model="form.user_id" filterable placeholder="选择本租户成员" style="width: 360px">
          <ElOption v-for="c in props.candidates" :key="c.id" :value="c.id" :label="`${c.name} · ${c.phone}`" />
        </ElSelect>
      </ElFormItem>
      <ElFormItem label="门店角色" :error="form.errors.role_id">
        <ElSelect v-model="form.role_id" placeholder="选择角色" style="width: 360px">
          <ElOption v-for="r in props.storeRoles" :key="r.id" :value="r.id"
                    :label="r.is_system ? `${r.name}（系统）` : r.name" />
        </ElSelect>
      </ElFormItem>
      <ElFormItem>
        <ElButton type="primary" :loading="form.processing" native-type="submit">加入</ElButton>
      </ElFormItem>
    </ElForm>
  </div>
</template>
