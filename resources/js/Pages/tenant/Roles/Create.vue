<script setup lang="ts">
/**
 * 租户后台「新建角色」页。
 * 权限按域分组（后端 groupedPermissions 注入），每组支持全选/清空。
 */
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import TenantLayout from '@/layouts/TenantLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { useI18n } from 'vue-i18n';
import { ElButton, ElInput, ElForm, ElFormItem, ElCheckbox, ElCheckboxGroup, ElCard, ElRadioGroup, ElRadio } from 'element-plus';

defineOptions({ layout: TenantLayout });

interface PageProps {
  groupedPermissions: Record<string, string[]>;
}

const page = usePage();
const props = computed(() => page.props as unknown as PageProps);
const { t } = useI18n();

const form = useForm({
  name: '',
  scope: 'store',
  permissions: [] as string[],
});

function submit() {
  form.post('/tenant/roles');
}

/**
 * 某组是否全选；用于"全选/清空"快捷按钮的状态显示。
 */
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
</script>

<template>
  <Head title="新建角色" />
  <PageHeader :breadcrumb="[{ label: '角色管理', to: '/tenant/roles' }, { label: '新建' }]">
    <template #actions>
      <ElButton @click="router.visit('/tenant/roles')">取消</ElButton>
      <ElButton type="primary" :loading="form.processing" @click="submit">保存</ElButton>
    </template>
  </PageHeader>
  <ElCard shadow="never" class="mt-3">
    <ElForm label-position="top">
      <ElFormItem label="作用域" :error="form.errors.scope">
        <ElRadioGroup v-model="form.scope">
          <ElRadio value="tenant">租户级（老板/财务总监）</ElRadio>
          <ElRadio value="store">门店级（店长/收银员/服务员）</ElRadio>
        </ElRadioGroup>
      </ElFormItem>
      <ElFormItem label="名称" :error="form.errors.name">
        <ElInput v-model="form.name" maxlength="50" show-word-limit style="max-width: 320px" />
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
                <div class="flex gap-3 text-[12px]">
                  <a class="text-[var(--primary)] cursor-pointer" @click="toggleGroup(domain, !groupAllChecked(domain))">
                    {{ groupAllChecked(domain) ? '清空' : '全选' }}
                  </a>
                </div>
              </div>
            </template>
            <ElCheckboxGroup v-model="form.permissions">
              <ElCheckbox v-for="p in items" :key="p" :value="p">{{ permLabel(p) }}</ElCheckbox>
            </ElCheckboxGroup>
          </ElCard>
        </div>
      </ElFormItem>
    </ElForm>
  </ElCard>
</template>
