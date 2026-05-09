<script setup lang="ts">
/**
 * 租户后台「编辑角色」页。
 * 系统角色（is_system=true）整页 readonly + 顶部黄色提示 + 提交按钮禁用。
 * 实际系统角色 tenant_id IS NULL，会被路由层 mustOwnedRole 直接 404；
 * 本视觉分支主要为防御兜底（数据异常或未来引入"租户专属系统角色"概念时）。
 */
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import TenantLayout from '@/layouts/TenantLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { useI18n } from 'vue-i18n';
import {
  ElButton, ElInput, ElForm, ElFormItem,
  ElCheckbox, ElCheckboxGroup, ElCard, ElAlert, ElRadioGroup, ElRadio,
} from 'element-plus';

defineOptions({ layout: TenantLayout });

interface EditRole {
  id: string;
  name: string;
  scope: string;
  permissions: string[];
  is_system: boolean;
}

interface PageProps {
  role: EditRole;
  groupedPermissions: Record<string, string[]>;
}

const page = usePage();
const props = computed(() => page.props as unknown as PageProps);
const { t } = useI18n();

const form = useForm({
  name: props.value.role.name,
  scope: props.value.role.scope,
  permissions: [...props.value.role.permissions],
});

function submit() {
  form.patch(`/tenant/roles/${props.value.role.id}`);
}

function groupAllChecked(group: string): boolean {
  const items = props.value.groupedPermissions[group] ?? [];
  return items.length > 0 && items.every((p) => form.permissions.includes(p));
}

function toggleGroup(group: string, on: boolean) {
  const items = props.value.groupedPermissions[group] ?? [];
  if (on) {
    const set = new Set(form.permissions);
    items.forEach((p) => set.add(p));
    form.permissions = Array.from(set);
  } else {
    form.permissions = form.permissions.filter((p) => !items.includes(p));
  }
}

function permLabel(p: string): string {
  const key = `permissions.items.${p}`;
  const tr = t(key);
  return tr === key ? p : tr;
}

function domainLabel(d: string): string {
  const key = `permissions.domains.${d}`;
  const tr = t(key);
  return tr === key ? d : tr;
}

const readOnly = computed(() => props.value.role.is_system);
</script>

<template>
  <Head title="编辑角色" />
  <PageHeader
    title="编辑角色"
    :breadcrumb="[{ label: '角色管理', to: '/tenant/roles' }, { label: props.role.name }]"
  />
  <div class="bg-white rounded-md p-6 shadow-[var(--shadow-card)]">
    <ElAlert
      v-if="readOnly" type="warning" :closable="false" show-icon
      title="系统内置角色不可修改" class="mb-4"
    />
    <ElForm label-position="top">
      <ElFormItem label="作用域" :error="form.errors.scope">
        <ElRadioGroup v-model="form.scope" :disabled="readOnly">
          <ElRadio value="tenant">租户级（老板/财务总监）</ElRadio>
          <ElRadio value="store">门店级（店长/收银员/服务员）</ElRadio>
        </ElRadioGroup>
      </ElFormItem>
      <ElFormItem label="名称" :error="form.errors.name">
        <ElInput v-model="form.name" :disabled="readOnly" maxlength="50" show-word-limit style="max-width: 320px" />
      </ElFormItem>
      <ElFormItem label="权限" :error="form.errors.permissions">
        <div class="flex flex-col gap-3 w-full">
          <ElCard
            v-for="(items, domain) in props.groupedPermissions" :key="domain"
            shadow="never" class="!border-[var(--border-soft)]"
          >
            <template #header>
              <div class="flex items-center justify-between">
                <span class="font-medium">{{ domainLabel(domain) }}</span>
                <div v-if="!readOnly" class="flex gap-3 text-[12px]">
                  <a class="text-[var(--primary)] cursor-pointer" @click="toggleGroup(domain, !groupAllChecked(domain))">
                    {{ groupAllChecked(domain) ? '清空' : '全选' }}
                  </a>
                </div>
              </div>
            </template>
            <ElCheckboxGroup v-model="form.permissions" :disabled="readOnly">
              <ElCheckbox v-for="p in items" :key="p" :value="p">{{ permLabel(p) }}</ElCheckbox>
            </ElCheckboxGroup>
          </ElCard>
        </div>
      </ElFormItem>
      <div class="flex justify-end gap-2 mt-4">
        <ElButton @click="router.visit('/tenant/roles')">取消</ElButton>
        <ElButton type="primary" :loading="form.processing" :disabled="readOnly" @click="submit">保存</ElButton>
      </div>
    </ElForm>
  </div>
</template>
