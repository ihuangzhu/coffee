<script setup lang="ts">
/**
 * 租户后台 - 编辑商品（单 SKU 同页）+ 状态切换。
 * 价格在 UI 用元（price_yuan），提交前 transform 为 sku.base_price_cents。
 */
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { ElButton, ElForm, ElFormItem, ElInput, ElInputNumber, ElOption, ElRadio, ElRadioGroup, ElSelect } from 'element-plus';
import TenantLayout from '@/layouts/TenantLayout.vue';
import PageHeader from '@/components/PageHeader.vue';

defineOptions({ layout: TenantLayout });

const page = usePage();
const props = computed(() => page.props as unknown as {
  goods: { id: string; name: string; category_id: string; status: 'active' | 'off_shelf' };
  sku: { id: string | null; base_price_cents: number; code: string | null; attrs_json: Record<string, unknown> | null };
  categories: Array<{ id: string; name: string }>;
});

const form = useForm({
  name: props.value.goods.name,
  category_id: props.value.goods.category_id,
  status: props.value.goods.status,
  price_yuan: props.value.sku.base_price_cents / 100,
  sku_code: props.value.sku.code ?? '',
});

function submit() {
  form.transform((d) => ({
    name: d.name,
    category_id: d.category_id,
    status: d.status,
    sku: {
      base_price_cents: Math.round((d.price_yuan ?? 0) * 100),
      code: d.sku_code || null,
      attrs_json: props.value.sku.attrs_json,
    },
  })).patch(`/tenant/goods/${props.value.goods.id}`);
}
function cancel() { router.visit('/tenant/goods'); }
</script>

<template>
  <Head title="编辑商品" />
  <PageHeader title="编辑商品" />
  <div class="bg-white rounded-md p-6 shadow-[var(--shadow-card)]" style="max-width: 720px">
    <ElForm label-width="100px">
      <ElFormItem label="商品名称" :error="form.errors.name" required>
        <ElInput v-model="form.name" maxlength="200" show-word-limit />
      </ElFormItem>
      <ElFormItem label="分类" :error="form.errors.category_id" required>
        <ElSelect v-model="form.category_id" style="width: 280px">
          <ElOption v-for="c in props.categories" :key="c.id" :label="c.name" :value="c.id" />
        </ElSelect>
      </ElFormItem>
      <ElFormItem label="售价(元)" :error="(form.errors as Record<string,string>)['sku.base_price_cents']" required>
        <ElInputNumber v-model="form.price_yuan" :min="0" :precision="2" :step="0.01" />
      </ElFormItem>
      <ElFormItem label="SKU 编码" :error="(form.errors as Record<string,string>)['sku.code']">
        <ElInput v-model="form.sku_code" maxlength="50" placeholder="选填" />
      </ElFormItem>
      <ElFormItem label="状态" :error="form.errors.status" required>
        <ElRadioGroup v-model="form.status">
          <ElRadio value="active">在售</ElRadio>
          <ElRadio value="off_shelf">下架</ElRadio>
        </ElRadioGroup>
      </ElFormItem>
      <ElFormItem>
        <ElButton type="primary" :loading="form.processing" @click="submit">保存</ElButton>
        <ElButton @click="cancel">取消</ElButton>
      </ElFormItem>
    </ElForm>
  </div>
</template>
