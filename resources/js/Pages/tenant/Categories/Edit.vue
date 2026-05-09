<script setup lang="ts">
/**
 * 租户后台 - 编辑商品分类。
 */
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { ElButton, ElForm, ElFormItem, ElInput, ElInputNumber } from 'element-plus';
import TenantLayout from '@/layouts/TenantLayout.vue';
import PageHeader from '@/components/PageHeader.vue';

defineOptions({ layout: TenantLayout });

const page = usePage();
const props = computed(() => page.props as unknown as {
  category: { id: string; name: string; sort: number };
});

const form = useForm({
  name: props.value.category.name,
  sort: props.value.category.sort,
});

function submit() { form.patch(`/tenant/categories/${props.value.category.id}`); }
function cancel() { router.visit('/tenant/categories'); }
</script>

<template>
  <Head title="编辑分类" />
  <PageHeader title="编辑商品分类" />
  <div class="bg-white rounded-md p-6 shadow-[var(--shadow-card)]" style="max-width: 560px">
    <ElForm label-width="100px">
      <ElFormItem label="分类名称" :error="form.errors.name" required>
        <ElInput v-model="form.name" maxlength="100" show-word-limit />
      </ElFormItem>
      <ElFormItem label="排序" :error="form.errors.sort">
        <ElInputNumber v-model="form.sort" :min="0" />
      </ElFormItem>
      <ElFormItem>
        <ElButton type="primary" :loading="form.processing" @click="submit">保存</ElButton>
        <ElButton @click="cancel">取消</ElButton>
      </ElFormItem>
    </ElForm>
  </div>
</template>
