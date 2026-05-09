<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core'
import { router } from '@inertiajs/vue3'
import { ElButton, ElForm, ElFormItem, ElInputNumber, ElOption, ElRadio, ElRadioGroup, ElSelect } from 'element-plus'
import { computed, reactive } from 'vue'

interface SkuOption {
  id: string
  sku_code: string
  spec_name: string
  item: { id: string; name: string }
}
interface StoreOption { id: string; name: string }

interface Props {
  outputSkus: SkuOption[]
  componentSkus: SkuOption[]
  stores: StoreOption[]
}
defineProps<Props>()

const form = reactive({
  output_sku_id: '',
  output_qty: 1,
  bom_type: 'STANDARD' as 'STANDARD' | 'STORE_CUSTOM',
  store_id: null as string | null,
  status: 'active' as 'active' | 'disabled',
  components: [
    { component_sku_id: '', consume_qty: 1, loss_rate: 0, sequence_no: 0 },
  ],
})

const isStoreCustom = computed(() => form.bom_type === 'STORE_CUSTOM')

function addRow() {
  form.components.push({ component_sku_id: '', consume_qty: 1, loss_rate: 0, sequence_no: form.components.length })
}
function removeRow(idx: number) {
  form.components.splice(idx, 1)
}

function submit() {
  if (form.bom_type === 'STANDARD') form.store_id = null
  router.post('/tenant/boms', form as unknown as Record<string, FormDataConvertible>)
}
</script>

<template>
  <div class="p-6 max-w-4xl">
    <h1 class="text-2xl font-bold mb-4">新建配方</h1>

    <ElForm :model="form" label-width="120px">
      <ElFormItem label="产出 SKU">
        <ElSelect v-model="form.output_sku_id" filterable placeholder="选择销售品 / 成品 / 半成品">
          <ElOption
            v-for="sku in outputSkus"
            :key="sku.id"
            :label="sku.item.name + ' / ' + sku.spec_name"
            :value="sku.id"
          />
        </ElSelect>
      </ElFormItem>

      <ElFormItem label="产出数量">
        <ElInputNumber v-model="form.output_qty" :min="0.0001" :step="1" :precision="4" />
      </ElFormItem>

      <ElFormItem label="配方类型">
        <ElRadioGroup v-model="form.bom_type">
          <ElRadio value="STANDARD">租户公共</ElRadio>
          <ElRadio value="STORE_CUSTOM">门店私有</ElRadio>
        </ElRadioGroup>
      </ElFormItem>

      <ElFormItem v-if="isStoreCustom" label="所属门店">
        <ElSelect v-model="form.store_id" placeholder="选择门店">
          <ElOption v-for="s in stores" :key="s.id" :label="s.name" :value="s.id" />
        </ElSelect>
      </ElFormItem>

      <ElFormItem label="状态">
        <ElRadioGroup v-model="form.status">
          <ElRadio value="active">启用</ElRadio>
          <ElRadio value="disabled">停用</ElRadio>
        </ElRadioGroup>
      </ElFormItem>

      <h2 class="text-lg font-semibold mt-6 mb-2">组件清单</h2>
      <table class="w-full border">
        <thead>
          <tr class="bg-gray-50">
            <th class="p-2 border">组件 SKU</th>
            <th class="p-2 border w-32">单份用量</th>
            <th class="p-2 border w-32">损耗率 (0~1)</th>
            <th class="p-2 border w-24">顺序</th>
            <th class="p-2 border w-20">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, idx) in form.components" :key="idx">
            <td class="p-2 border">
              <ElSelect v-model="row.component_sku_id" filterable placeholder="选择原料 / 半成品 / 包材">
                <ElOption
                  v-for="sku in componentSkus"
                  :key="sku.id"
                  :label="sku.item.name + ' / ' + sku.spec_name"
                  :value="sku.id"
                />
              </ElSelect>
            </td>
            <td class="p-2 border">
              <ElInputNumber v-model="row.consume_qty" :min="0.0001" :precision="4" />
            </td>
            <td class="p-2 border">
              <ElInputNumber v-model="row.loss_rate" :min="0" :max="1" :step="0.01" :precision="4" />
            </td>
            <td class="p-2 border">
              <ElInputNumber v-model="row.sequence_no" :min="0" :step="1" />
            </td>
            <td class="p-2 border text-center">
              <ElButton size="small" type="danger" @click="removeRow(idx)">删除</ElButton>
            </td>
          </tr>
        </tbody>
      </table>
      <ElButton class="mt-2" @click="addRow">+ 添加组件</ElButton>

      <div class="mt-6">
        <ElButton type="primary" @click="submit">保存</ElButton>
      </div>
    </ElForm>
  </div>
</template>
