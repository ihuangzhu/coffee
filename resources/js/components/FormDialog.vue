<script setup lang="ts">
import { ElDialog, ElButton } from 'element-plus';

defineProps<{
  modelValue: boolean;
  title: string;
  width?: string | number;
  loading?: boolean;
  submitText?: string;
  cancelText?: string;
}>();

const emit = defineEmits<{
  'update:modelValue': [v: boolean];
  'submit': [];
  'cancel': [];
}>();

// 关闭对话框：同步触发 update:modelValue=false 与 cancel 事件，未调用 el-dialog 的 done 回调
function close() { emit('update:modelValue', false); emit('cancel'); }
function submit() { emit('submit'); }
</script>

<template>
  <el-dialog
    :model-value="modelValue" :title="title" :width="width ?? '480px'"
    :before-close="close" align-center>
    <slot />
    <template #footer>
      <slot name="footer">
        <el-button @click="close">{{ cancelText ?? '取消' }}</el-button>
        <el-button type="primary" :loading="loading" @click="submit">
          {{ submitText ?? '确定' }}
        </el-button>
      </slot>
    </template>
  </el-dialog>
</template>
