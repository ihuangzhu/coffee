<script setup lang="ts" generic="T extends Record<string, unknown>">
import { ElTable, ElTableColumn, ElPagination } from 'element-plus';

/**
 * DataTable：业务列表通用表格组件。
 * Slate × Indigo 升级：白色 card + slate-50 表头 + slate-50 hover 染行；移除 stripe。
 * 末列 actions 由调用方走文字链接形式（spec §3.5）。
 */
interface Column {
  key: string;
  label: string;
  width?: number | string;
  minWidth?: number | string;
  sortable?: boolean;
  align?: 'left' | 'center' | 'right';
  formatter?: (row: T) => string;
}

withDefaults(defineProps<{
  rows: T[];
  total: number;
  columns: Column[];
  page: number;
  pageSize: number;
  sort?: { field: string; order: 'asc' | 'desc' } | null;
  selectable?: boolean;
  selected?: (string | number)[];
  loading?: boolean;
  tableId?: string;
  actionsWidth?: number | string;
  /** seamless：去除外圈边框/圆角，让表格作为页面 chrome 的延伸（list 页推荐） */
  seamless?: boolean;
}>(), {
  actionsWidth: 200,
  seamless: false,
});

const emit = defineEmits<{
  'update:page': [n: number];
  'update:pageSize': [n: number];
  'update:sort': [s: { field: string; order: 'asc' | 'desc' } | null];
  'update:selected': [ids: (string | number)[]];
  'row-click': [row: T];
}>();

function onSortChange(p: { prop: string; order: string | null }) {
  if (!p.order) emit('update:sort', null);
  else emit('update:sort', { field: p.prop, order: p.order === 'ascending' ? 'asc' : 'desc' });
}

function onSelectionChange(rows: T[]) {
  emit('update:selected', rows.map((r) => r['id'] as string | number));
}
</script>

<template>
  <div class="data-table overflow-hidden"
       :style="seamless
         ? { background: 'var(--el-bg-color)' }
         : { background: 'var(--el-bg-color)',
             border: '1px solid var(--el-border-color-lighter)',
             borderRadius: 'var(--el-border-radius-base)' }">
    <el-table
      :data="rows" :loading="loading"
      style="width: 100%" row-key="id"
      header-cell-class-name="dt-head-cell"
      cell-class-name="dt-body-cell"
      @sort-change="onSortChange" @selection-change="onSelectionChange"
      @row-click="(row: T) => emit('row-click', row)">
      <el-table-column v-if="selectable" type="selection" width="48" />
      <el-table-column
        v-for="col in columns" :key="col.key"
        :prop="col.key" :label="col.label" :width="col.width" :min-width="col.minWidth"
        :sortable="col.sortable ? 'custom' : false" :align="col.align ?? 'left'"
        :formatter="col.formatter ? (row: T) => col.formatter!(row) : undefined">
        <template v-if="$slots[`col-${col.key}`]" #default="{ row, $index }">
          <slot :name="`col-${col.key}`" :row="row" :index="$index" />
        </template>
      </el-table-column>
      <el-table-column v-if="$slots.actions" label="" :width="actionsWidth" align="right">
        <template #default="{ row }"><slot name="actions" :row="row" /></template>
      </el-table-column>
      <template #empty>
        <slot name="empty">
          <div class="py-12 text-[13px]" style="color: var(--text-faint)">暂无数据</div>
        </slot>
      </template>
    </el-table>
    <div class="flex items-center justify-between px-5 py-2.5 border-t text-[12.5px]"
         style="border-color: var(--el-border-color-lighter); color: var(--el-text-color-secondary)">
      <span>共 <span class="font-mono font-medium" style="color: var(--text)">{{ total }}</span> 条</span>
      <el-pagination
        :current-page="page" :page-size="pageSize" :total="total"
        :page-sizes="[20, 50, 100]" layout="sizes, prev, pager, next, jumper"
        @current-change="(n: number) => emit('update:page', n)"
        @size-change="(n: number) => emit('update:pageSize', n)" />
    </div>
  </div>
</template>

<style>
/* 表头与单元格走 spec §3.5 规格；用全局 style 覆盖 EP class 而非 scoped，
   因为 EP table 的 thead/td 不在 scoped 选择器范围内。 */
.dt-head-cell {
  background: var(--slate-50) !important;
  color: var(--text-muted) !important;
  font-weight: 500 !important;
  font-size: 11.5px !important;
  letter-spacing: .02em;
}
.dt-body-cell {
  font-size: 13px !important;
  color: var(--text) !important;
  border-bottom: 1px solid var(--border-soft) !important;
}
.el-table__row:hover > td.el-table__cell {
  background: var(--slate-50) !important;
}
</style>
