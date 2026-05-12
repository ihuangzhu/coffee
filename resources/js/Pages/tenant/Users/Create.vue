<script setup lang="ts">
/**
 * 租户后台「新建用户」页（邀请加入租户）。
 * tenant_role_id 可选：留空表示纯门店级身份（只在某门店有岗位）。
 * 后端 UsersController::store 内含「邀请即覆盖」：手机号已存在的 user 会被
 * 从其它租户的 active membership 中清退（含 store_user 级联）。
 */
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import TenantLayout from '@/layouts/TenantLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { ElButton, ElInput, ElForm, ElFormItem, ElSelect, ElOption, ElCard } from 'element-plus';

defineOptions({ layout: TenantLayout });

interface RoleOption { id: string; name: string; is_system: boolean }

const page = usePage();
const props = computed(() => page.props as unknown as { tenantRoles: RoleOption[] });

const form = useForm({
  name: '',
  phone: '',
  password: '',
  tenant_role_id: '',
});

function submit() {
  form.post('/tenant/users');
}
</script>

<template>
  <Head title="新建用户" />
  <PageHeader :breadcrumb="[{ label: '用户管理', to: '/tenant/users' }, { label: '新建' }]">
    <template #actions>
      <ElButton @click="router.visit('/tenant/users')">取消</ElButton>
      <ElButton type="primary" :loading="form.processing" @click="submit">保存</ElButton>
    </template>
  </PageHeader>
  <ElCard shadow="never" class="mt-3 max-w-xl">
    <ElForm label-position="top">
      <ElFormItem label="姓名" :error="form.errors.name">
        <ElInput v-model="form.name" />
      </ElFormItem>
      <ElFormItem label="手机号" :error="form.errors.phone">
        <ElInput v-model="form.phone" />
      </ElFormItem>
      <ElFormItem label="初始密码" :error="form.errors.password">
        <ElInput v-model="form.password" type="password" show-password />
      </ElFormItem>
      <ElFormItem label="租户级角色（可选）" :error="form.errors.tenant_role_id">
        <ElSelect v-model="form.tenant_role_id" placeholder="请选择租户级角色" clearable style="width: 100%">
          <ElOption
            v-for="r in props.tenantRoles" :key="r.id" :value="r.id"
            :label="r.is_system ? `${r.name}（内置）` : r.name"
          />
        </ElSelect>
      </ElFormItem>
    </ElForm>
  </ElCard>
</template>
