<script setup lang="ts">
/**
 * 租户后台「编辑用户」页。
 * 排版规范见 docs/superpowers/specs/2026-05-09-page-layout-spec-design.md
 * 左 7-8 表单卡 + 右 4-5 Identity / Danger 双卡 + 底部 sticky ActionBar。
 * 一律走 Element Plus 原生组件。
 */
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import TenantLayout from '@/layouts/TenantLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import {
  ElButton, ElInput, ElForm, ElFormItem, ElSelect, ElOption,
  ElCard, ElAvatar, ElDescriptions, ElDescriptionsItem, ElDivider,
  ElMessage, ElMessageBox,
} from 'element-plus';

defineOptions({ layout: TenantLayout });

interface EditUser {
  id: string;
  name: string;
  phone: string;
  tenant_role_id: string | null;
  created_at: string | null;
  last_login_at: string | null;
  store_binding_count: number;
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

function fmtDateTime(iso: string | null): string {
  if (!iso) return '—';
  return iso.substring(0, 19).replace('T', ' ');
}
function fmtDate(iso: string | null): string {
  if (!iso) return '—';
  return iso.substring(0, 10);
}
function fmtPhone(p: string): string {
  return p.length === 11 ? `${p.slice(0, 3)}****${p.slice(7)}` : p;
}

const initial = computed(() => user.value.name?.trim().charAt(0) ?? '?');

function resetPassword() {
  ElMessageBox.prompt(`为 ${user.value.name} 设置新密码（至少 8 位）`, '重置密码', {
    inputType: 'password',
    inputValidator: (v: string) => (v && v.length >= 8) || '密码至少 8 位',
    confirmButtonText: '重置',
    cancelButtonText: '取消',
  }).then(({ value }) => {
    router.post(`/tenant/users/${user.value.id}/reset-password`, { password: value }, {
      preserveScroll: true,
      onSuccess: () => ElMessage.success('密码已重置'),
    });
  }).catch(() => { /* 取消 */ });
}

function destroy() {
  ElMessageBox.confirm(
    `删除后该用户在本租户的全部成员关系与角色绑定将被撤销。确认删除「${user.value.name}」？`,
    '危险操作',
    { type: 'warning', confirmButtonText: '确认删除', cancelButtonText: '取消' },
  ).then(() => router.delete(`/tenant/users/${user.value.id}`))
   .catch(() => { /* 取消 */ });
}
</script>

<template>
  <Head title="编辑用户" />
  <PageHeader
    :breadcrumb="[{ label: '用户管理', to: '/tenant/users' }, { label: user.name }]"
  >
    <template #actions>
      <ElButton @click="router.visit('/tenant/users')">取消</ElButton>
      <ElButton type="primary" :loading="form.processing" @click="submit">保存修改</ElButton>
    </template>
  </PageHeader>

  <div class="grid grid-cols-12 gap-4 mt-3">
    <!-- 左：表单卡 -->
    <section class="col-span-12 lg:col-span-8 xl:col-span-7">
      <ElCard shadow="never">
        <template #header>
          <span class="text-[14px] font-semibold">基本信息</span>
        </template>
        <ElForm label-position="top" @submit.prevent>
          <ElFormItem label="姓名" :error="form.errors.name">
            <ElInput v-model="form.name" />
          </ElFormItem>
          <ElFormItem label="手机号（登录账号）">
            <ElInput :model-value="user.phone" disabled />
          </ElFormItem>
        </ElForm>
      </ElCard>

      <ElCard shadow="never" class="mt-4">
        <template #header>
          <span class="text-[14px] font-semibold">权限</span>
        </template>
        <ElForm label-position="top" @submit.prevent>
          <ElFormItem label="租户级角色（可选）" :error="form.errors.tenant_role_id">
            <ElSelect v-model="form.tenant_role_id" placeholder="请选择租户级角色" clearable style="width: 100%">
              <ElOption
                v-for="r in tenantRoles" :key="r.id" :value="r.id"
                :label="r.is_system ? `${r.name}（内置）` : r.name"
              />
            </ElSelect>
            <div class="el-form-item__description">
              清空后该用户仅以门店级岗位身份在所属门店工作。
            </div>
          </ElFormItem>
        </ElForm>
      </ElCard>
    </section>

    <!-- 右：Identity + Danger -->
    <aside class="col-span-12 lg:col-span-4 xl:col-span-5 space-y-4">
      <ElCard shadow="never">
        <div class="flex items-center gap-3">
          <ElAvatar :size="44" style="background: var(--primary)">{{ initial }}</ElAvatar>
          <div class="min-w-0">
            <div class="text-[15px] font-semibold truncate">{{ user.name }}</div>
            <div class="text-[12.5px] text-[var(--el-text-color-secondary)] tabular-nums">
              {{ fmtPhone(user.phone) }}
            </div>
          </div>
        </div>
        <ElDivider class="!my-4" />
        <ElDescriptions :column="1" size="small">
          <ElDescriptionsItem label="创建于">
            <span class="tabular-nums">{{ fmtDate(user.created_at) }}</span>
          </ElDescriptionsItem>
          <ElDescriptionsItem label="上次登录">
            <span class="tabular-nums">{{ fmtDateTime(user.last_login_at) }}</span>
          </ElDescriptionsItem>
          <ElDescriptionsItem label="门店绑定">
            <span class="tabular-nums">{{ user.store_binding_count }} 个门店</span>
          </ElDescriptionsItem>
        </ElDescriptions>
      </ElCard>

      <ElCard shadow="never">
        <template #header>
          <span class="text-[14px] font-semibold">危险区</span>
        </template>
        <div class="flex items-center justify-between">
          <div>
            <div class="text-[13px]">重置密码</div>
            <div class="text-[12px] text-[var(--el-text-color-secondary)]">
              为该用户设置新的登录密码
            </div>
          </div>
          <ElButton size="small" @click="resetPassword">重置</ElButton>
        </div>
        <ElDivider class="!my-3" />
        <div class="flex items-center justify-between">
          <div>
            <div class="text-[13px]">删除用户</div>
            <div class="text-[12px] text-[var(--el-text-color-secondary)]">
              撤销该用户在本租户的全部关系
            </div>
          </div>
          <ElButton size="small" type="danger" plain @click="destroy">删除</ElButton>
        </div>
      </ElCard>
    </aside>
  </div>

</template>

<style scoped>
.el-form-item__description {
  margin-top: 6px;
  font-size: 12.5px;
  color: var(--el-text-color-secondary);
  line-height: 1.5;
}
</style>
