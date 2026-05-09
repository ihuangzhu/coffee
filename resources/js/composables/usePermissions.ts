import type { PageProps } from '@inertiajs/core';

/**
 * 判断 Inertia SharedProps.permissions 是否包含给定权限码。
 * - permissions 由 HandleInertiaRequests::share 注入，类型 string[]
 * - 缺省（未注入或非数组）一律视为无权限，避免误授权
 */
export function hasPermission(page: { props: PageProps }, key: string): boolean {
  const perms = (page.props as { permissions?: unknown }).permissions;
  return Array.isArray(perms) && perms.includes(key);
}
