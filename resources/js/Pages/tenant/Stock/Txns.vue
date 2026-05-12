<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ElButton, ElSelect, ElOption, ElTag, ElMessageBox } from 'element-plus';
import TenantLayout from '@/layouts/TenantLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DataTable from '@/components/DataTable.vue';
import { stockTxnBizTypeLabel, stockTxnDirectionLabel } from '@/lib/itemTypes';
import dayjs from 'dayjs';

function fmtTime(s: string | null): string {
  if (!s) return '';
  const d = dayjs(s);
  return d.isValid() ? d.format('YYYY-MM-DD HH:mm') : s;
}

defineOptions({ layout: TenantLayout });

interface Row extends Record<string, unknown> {
  id: number; biz_type: string; direction: string;
  qty_change: number; sku_id: string; item_name: string;
  occurred_at: string | null;
  unit_cost_cents: number | null; amount_cents: number | null;
  meta: Record<string, unknown> | null;
  is_cancelled: boolean; is_reversal: boolean;
}

const page = usePage();
const props = computed(() => page.props as unknown as {
  rows: Row[]; total: number; page: number; pageSize: number;
  biz_type: string; sku_id: string; biz_types: string[];
});

const bizType = ref(props.value.biz_type);

function reload(params: Record<string, unknown> = {}) {
  router.get('/tenant/stock/txns', {
    biz_type: bizType.value, sku_id: props.value.sku_id,
    page: props.value.page, per_page: props.value.pageSize, ...params,
  }, { preserveState: true, preserveScroll: true });
}

function onPage(n: number) { reload({ page: n }); }
function onPageSize(n: number) { reload({ page: 1, per_page: n }); }

async function reverse(row: Row) {
  try {
    await ElMessageBox.confirm(`确认撤销流水 #${row.id}？`, '撤销', { type: 'warning' });
  } catch { return; }
  router.post(`/tenant/stock/txns/${row.id}/reverse`, {},
    { preserveScroll: true, onSuccess: () => reload() });
}

const columns = [
  { key: 'id', label: '#', width: 80 },
  { key: 'biz_type', label: '类型', width: 140,
    formatter: (r: Row) => stockTxnBizTypeLabel(r.biz_type) },
  { key: 'direction', label: '方向', width: 80,
    formatter: (r: Row) => stockTxnDirectionLabel(r.direction) },
  { key: 'qty_change', label: '数量', width: 100, align: 'right' as const },
  { key: 'item_name', label: '物料', minWidth: 180 },
  { key: 'occurred_at', label: '时间', width: 160,
    formatter: (r: Row) => fmtTime(r.occurred_at) },
  { key: 'state', label: '状态', width: 100 },
];
</script>

<template>
  <Head title="库存流水" />
  <PageHeader>
    <template #filter>
      <ElSelect v-model="bizType" placeholder="业务类型" style="width: 180px"
        @change="reload({ page: 1 })">
        <ElOption label="全部类型" value="all" />
        <ElOption v-for="t in props.biz_types" :key="t" :value="t" :label="stockTxnBizTypeLabel(t)" />
      </ElSelect>
    </template>
  </PageHeader>

  <div class="mt-3">
    <DataTable
      :rows="props.rows"
      :total="props.total"
      :page="props.page"
      :page-size="props.pageSize"
      :columns="columns"
      :actions-width="80"
      @update:page="onPage"
      @update:pageSize="onPageSize"
    >
      <template #col-id="{ row }">
        <span class="font-mono">{{ (row as Row).id }}</span>
      </template>
      <template #col-state="{ row }">
        <ElTag v-if="(row as Row).is_reversal" size="small" type="warning">撤销笔</ElTag>
        <ElTag v-else-if="(row as Row).is_cancelled" size="small" type="info">已撤销</ElTag>
        <ElTag v-else size="small" type="success">有效</ElTag>
      </template>
      <template #actions="{ row }">
        <ElButton v-if="!(row as Row).is_cancelled && !(row as Row).is_reversal"
          link size="small" type="danger" @click="reverse(row as Row)">撤销</ElButton>
      </template>
      <template #empty>
        <div class="py-12 text-[13px] text-center" style="color: var(--text-faint)">
          暂无流水
        </div>
      </template>
    </DataTable>
  </div>
</template>
