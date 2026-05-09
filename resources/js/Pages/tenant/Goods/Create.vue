<script setup lang="ts">
/**
 * 租户后台 - 新建商品（单 SKU 同页）。
 * 价格在 UI 用元（price_yuan），提交前 transform 为 sku.base_price_cents = round(yuan*100)。
 */
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { ElButton, ElForm, ElFormItem, ElInput, ElInputNumber, ElOption, ElSelect } from 'element-plus';
import TenantLayout from '@/layouts/TenantLayout.vue';
import PageHeader from '@/components/PageHeader.vue';

defineOptions({ layout: TenantLayout });

const page = usePage();
const props = computed(() => page.props as unknown as {
  categories: Array<{ id: string; name: string }>;
});

const form = useForm({
  name: '',
  category_id: '',
  price_yuan: 0,
  sku_code: '',
});

function submit() {
  form.transform((d) => ({
    name: d.name,
    category_id: d.category_id,
    sku: {
      base_price_cents: Math.round((d.price_yuan ?? 0) * 100),
      code: d.sku_code || null,
      attrs_json: null,
    },
  })).post('/tenant/goods');
}

function cancel() { router.visit('/tenant/goods'); }
</script>

<template>
  <Head title="新建商品" />
  <PageHeader title="新建商品" />
  <div class="bg-white rounded-md p-6 shadow-[var(--shadow-card)]" style="max-width: 720px">
    <ElForm label-width="100px">
      <ElFormItem label="商品名称" :error="form.errors.name" required>
        <ElInput v-model="form.name" maxlength="200" show-word-limit />
      </ElFormItem>
      <ElFormItem label="分类" :error="form.errors.category_id" required>
        <ElSelect v-model="form.category_id" placeholder="请选择分类" style="width: 280px">
          <ElOption v-for="c in props.categories" :key="c.id" :label="c.name" :value="c.id" />
        </ElSelect>
      </ElFormItem>
      <ElFormItem label="售价(元)" :error="(form.errors as Record<string,string>)['sku.base_price_cents']" required>
        <ElInputNumber v-model="form.price_yuan" :min="0" :precision="2" :step="0.01" />
      </ElFormItem>
      <ElFormItem label="SKU 编码" :error="(form.errors as Record<string,string>)['sku.code']">
        <ElInput v-model="form.sku_code" maxlength="50" placeholder="选填" />
      </ElFormItem>
      <ElFormItem>
        <ElButton type="primary" :loading="form.processing" :disabled="props.categories.length === 0" @click="submit">创建</ElButton>
        <ElButton @click="cancel">取消</ElButton>
      </ElFormItem>
    </ElForm>
  </div>
</template>
