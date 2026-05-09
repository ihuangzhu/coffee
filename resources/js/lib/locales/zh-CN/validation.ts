/**
 * 表单校验提示文案 - 与 useForm validators 配合。
 * 占位 {n} 用 vue-i18n 命名插值：t('validation.min_length', { n: 6 })。
 */
export default {
  required: '必填项',
  email: '请输入有效邮箱',
  phone: '请输入有效手机号',
  min_length: '至少 {n} 个字符',
  max_length: '最多 {n} 个字符',
};
