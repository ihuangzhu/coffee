<script setup lang="ts">
/**
 * 租户后台「编辑用户」页。
 * tenant_role_id 可改可清空（清空表示该用户在本租户仅作为门店级岗位人员）。
 */
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import TenantLayout from '@/layouts/TenantLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { ElButton, ElInput, ElForm, ElFormItem, ElSelect, ElOption } from 'element-plus';

defineOptions({ layout: TenantLayout });

interface EditUser {
  id: string;
  name: string;
  phone: string;
  tenant_role_id: string | null;
}
interface RoleOption { id: string; name: string; is_system: boolean }

const page = usePage();
const user = computed(() => (page.props as unknown as { user: EditUser }).user);
const tenantRoles = computed(() => (page.props as unknown as { tenantRoles: RoleOption[] }).tenantRoles);

const form = useForm({
  name: user.value.name,
  tenant_role_id: user.value.tenant_role_id ?? '',
});

function submit() {
  form.patch(`/tenant/users/${user.value.id}`);
}
</script>

<template>
  <Head title="编辑用户" />
  <PageHeader
    title="编辑用户"
    :breadcrumb="[{ label: '用户管理', to: '/tenant/users' }, { label: user.name }]"
  />
  <div class="max-w-xl bg-white rounded-md p-6 shadow-[var(--shadow-card)]">
    <ElForm label-position="top">
      <ElFormItem label="姓名" :error="form.errors.name">
        <ElInput v-model="form.name" />
      </ElFormItem>
      <ElFormItem label="手机号">
        <ElInput :model-value="user.phone" disabled />
      </ElFormItem>
      <ElFormItem label="租户级角色（可选）" :error="form.errors.tenant_role_id">
        <ElSelect v-model="form.tenant_role_id" placeholder="请选择租户级角色" clearable style="width: 100%">
          <ElOption
            v-for="r in tenantRoles" :key="r.id" :value="r.id"
            :label="r.is_system ? `${r.name}（内置）` : r.name"
          />
        </ElSelect>
      </ElFormItem>
      <div class="flex justify-end gap-2 mt-4">
        <ElButton @click="router.visit('/tenant/users')">取消</ElButton>
        <ElButton type="primary" :loading="form.processing" @click="submit">保存</ElButton>
      </div>
    </ElForm>
  </div>
</template>
