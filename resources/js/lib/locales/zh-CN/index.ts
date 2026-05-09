/**
 * zh-CN locale 入口聚合文件。
 *
 * 结构约定：
 * - 顶层按"域"拆分：common / auth / nav / validation / permissions
 * - 页面专属文案集中在 pages/* 子目录，按页面切分以降低单文件膨胀
 * - 新增模块（UI-1+）应在 pages/ 下加一个文件并在此 import + 挂到 pages 命名空间
 */
import auth from './auth';
import common from './common';
import nav from './nav';
import permissions from './permissions';
import login from './pages/login';
import profile from './pages/profile';
import roles from './pages/roles';
import selectTenant from './pages/select-tenant';
import users from './pages/users';
import validation from './validation';

export default {
  common,
  auth,
  nav,
  validation,
  permissions,
  pages: {
    login,
    profile,
    users,
    roles,
    select_tenant: selectTenant,
  },
};
