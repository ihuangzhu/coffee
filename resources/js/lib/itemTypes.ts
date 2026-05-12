/**
 * 后端枚举 → 中文标签 helper。
 * 实际文案在 lib/locales/zh-CN/enums.ts 维护；此处只是按域包了一层 t()，
 * 让 .ts 业务逻辑里也能拿到翻译（vue-i18n 的 $t 只在 setup/template 里方便用）。
 *
 * 模板里两种写法等价：
 *   {{ itemTypeLabel(row.item_type) }}
 *   {{ $t('enums.item_type.' + row.item_type) }}
 *
 * 未知 code 走 i18n missing 兜底（vue-i18n 默认返回 key 本身），
 * 我们这里再做一次回退：返回原始 code 而不是 'enums.item_type.XXX'。
 */
import i18n from './i18n';

function tEnum(domain: string, code: string): string {
  if (!code) return code;
  const key = `enums.${domain}.${code}`;
  const translated = i18n.global.t(key);
  // 命中失败时 vue-i18n 返回 key 字面量；此时降级为 code 本身
  return translated === key ? code : translated;
}

export function itemTypeLabel(t: string): string {
  return tEnum('item_type', t);
}

export function itemTypeScopeLabel(t: string): string {
  return tEnum('item_type_scope', t);
}

export function categoryTypeLabel(t: string): string {
  return tEnum('category_type', t);
}

export function ownerTypeLabel(t: string): string {
  return tEnum('owner_type', t);
}

export function stockTxnBizTypeLabel(t: string): string {
  return tEnum('stock_txn_biz_type', t);
}

export function stockTxnDirectionLabel(t: string): string {
  return tEnum('stock_txn_direction', t);
}

/**
 * 给 v-for 列出枚举字典使用：返回 { CODE: '中文' } 对象。
 * 用在 Categories/{Create,Edit} 的「挂载范围」下拉，避免在模板里手写 codes 列表。
 */
export function enumDict(domain: string): Record<string, string> {
  const messages = i18n.global.getLocaleMessage(i18n.global.locale.value) as Record<string, unknown>;
  const enumsRoot = messages?.enums as Record<string, Record<string, string>> | undefined;
  return enumsRoot?.[domain] ?? {};
}

