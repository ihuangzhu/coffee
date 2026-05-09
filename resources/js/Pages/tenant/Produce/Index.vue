<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core'
import { router } from '@inertiajs/vue3'
import axios from '@/lib/axios'
import {
  ElButton,
  ElCollapse,
  ElCollapseItem,
  ElForm,
  ElFormItem,
  ElInputNumber,
  ElMessage,
  ElOption,
  ElSelect,
  ElTable,
  ElTableColumn,
  ElTag,
} from 'element-plus'
import { computed, reactive, ref, watch } from 'vue'

interface StoreOption {
  id: string
  name: string
}
interface BomOption {
  id: string
  output_sku_id: string
  output_qty: string
  bom_type: 'STANDARD' | 'STORE_CUSTOM'
  store_id: string | null
  output_sku: { item: { name: string }; spec_name: string }
}
interface PreviewConsume {
  sku_id: string
  sku_name: string
  needed: string
  available: string
  sufficient: boolean
}
interface Preview {
  output: { sku_id: string; sku_name: string; qty: string }
  consumes: PreviewConsume[]
}

interface Props {
  stores: StoreOption[]
  boms: BomOption[]
}
const props = defineProps<Props>()

const form = reactive({
  store_id: '',
  bom_id: '',
  batch_qty: 1,
  source_location_id: null as string | null,
  output_location_id: null as string | null,
})

const eligibleBoms = computed(() =>
  props.boms.filter(
    (b) =>
      b.bom_type === 'STANDARD' ||
      (b.bom_type === 'STORE_CUSTOM' && b.store_id === form.store_id),
  )
)

const preview = ref<Preview | null>(null)
const allSufficient = computed(
  () => preview.value?.consumes.every((c) => c.sufficient) ?? false,
)

async function fetchPreview() {
  if (!form.store_id || !form.bom_id || form.batch_qty <= 0) {
    preview.value = null
    return
  }
  try {
    const { data } = await axios.get<Preview>('/tenant/produce/preview', {
      params: {
        store_id: form.store_id,
        bom_id: form.bom_id,
        batch_qty: form.batch_qty,
      },
    })
    preview.value = data
  } catch {
    preview.value = null
    ElMessage.error('预览失败，请检查输入')
  }
}

watch(() => [form.store_id, form.bom_id, form.batch_qty] as const, fetchPreview)

function submit() {
  if (!allSufficient.value) {
    ElMessage.warning('原料库存不足，无法生产')
    return
  }
  router.post('/tenant/produce', form as unknown as Record<string, FormDataConvertible>)
}
</script>

<template>
  <div class="p-6 max-w-5xl">
    <h1 class="text-2xl font-bold mb-4">生产入库</h1>

    <ElForm :model="form" label-width="120px">
      <ElFormItem label="门店">
        <ElSelect v-model="form.store_id">
          <ElOption v-for="s in stores" :key="s.id" :label="s.name" :value="s.id" />
        </ElSelect>
      </ElFormItem>

      <ElFormItem label="配方">
        <ElSelect v-model="form.bom_id" filterable>
          <ElOption
            v-for="b in eligibleBoms"
            :key="b.id"
            :label="b.output_sku.item.name + ' / ' + b.output_sku.spec_name + ' (×' + b.output_qty + ')'"
            :value="b.id"
          />
        </ElSelect>
      </ElFormItem>

      <ElFormItem label="生产批次数">
        <ElInputNumber v-model="form.batch_qty" :min="0.0001" :precision="4" />
      </ElFormItem>

      <ElCollapse>
        <ElCollapseItem title="高级：自定义库位（默认 DEFAULT）">
          <ElFormItem label="原料出库位 ID">
            <input
              v-model="form.source_location_id"
              class="border p-1"
              placeholder="留空 = DEFAULT"
            />
          </ElFormItem>
          <ElFormItem label="成品入库位 ID">
            <input
              v-model="form.output_location_id"
              class="border p-1"
              placeholder="留空 = DEFAULT"
            />
          </ElFormItem>
        </ElCollapseItem>
      </ElCollapse>
    </ElForm>

    <div v-if="preview" class="mt-6">
      <h2 class="text-lg font-semibold mb-2">预览</h2>
      <p class="mb-2">
        将产出：<strong>{{ preview.output.sku_name }}</strong> × {{ preview.output.qty }}
      </p>
      <ElTable :data="preview.consumes" border>
        <ElTableColumn label="原料" prop="sku_name" min-width="240" />
        <ElTableColumn label="实际消耗（含损耗）" prop="needed" width="160" />
        <ElTableColumn label="当前库存" prop="available" width="140" />
        <ElTableColumn label="状态" width="100">
          <template #default="{ row }">
            <ElTag v-if="row.sufficient" type="success">充足</ElTag>
            <ElTag v-else type="danger">不足</ElTag>
          </template>
        </ElTableColumn>
      </ElTable>
    </div>

    <div class="mt-6">
      <ElButton type="primary" :disabled="!preview || !allSufficient" @click="submit">
        提交生产
      </ElButton>
    </div>
  </div>
</template>
