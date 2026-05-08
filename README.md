# Coffee — 咖啡连锁 SaaS（首期：身份骨架）

> 基于 supermarket 框架移植，按 base.md §1~§2 / §6.1 实现的最小可跑多租户身份骨架。

## 范围

首期实现：

- **5 张表**：tenants / stores / users / memberships / personal_access_tokens
- **9 个 API 端点**：登录 / 登出 / me / me/memberships / tenants/current / stores / 平台 3 个创建端点
- **3 个前端页面**：登录 / 选租户 / 主页
- **1 个 CLI**：`php artisan coffee:bootstrap` 初始化首个平台员工

后续迭代（不在首期）：RBAC / 数据权限范围 / audit_log / 套餐计费 / 任何咖啡业务模块。

## 快速开始

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate

# 一键填充开发数据（含平台员工 + 2 租户 + 4 门店 + 多个员工与 memberships）
php artisan db:seed
# 重置并重填：php artisan migrate:fresh --seed

# 也可单独创建首个平台员工（不需要 seeder 时）
php artisan coffee:bootstrap --phone=13800000000 --password=secret123

# 启动开发环境（后端 + 队列 + 日志 + 前端）
composer dev
```

## 浏览器验证 happy path

1. 访问 http://localhost:8000/login，用平台员工 13800000000 登录。
2. 平台员工通过 curl/Postman 调 `/api/platform/tenants` 创建租户、`/api/platform/tenants/{id}/users` 创建员工。
3. 用员工账号登录，进入 `/select-tenant` 选择租户，进入主页。

> 已跑 `db:seed` 的话，可以直接用以下账号登录（密码统一 `secret123`）：
>
> | 角色 | 手机号 | 说明 |
> | --- | --- | --- |
> | 平台员工 | `13800000000` | 创建租户/门店/用户 |
> | 九号咖啡 admin | `13900000001` | 租户级管理员 |
> | 九号咖啡 徐汇店店长 | `13900000002` | 门店级 |
> | 九号咖啡 朝阳店店长 | `13900000004` | 门店级 |
> | 示范咖啡 admin | `13900000011` | 租户级管理员 |

## 测试

```bash
./vendor/bin/pest                       # 所有 Pest 测试
./vendor/bin/pint --test                # PHP 格式校验
npx vue-tsc --noEmit                    # 前端类型检查
npm run build                           # 前端构建
```

## 架构

- 后端：Laravel 13 / PHP 8.3+ / Sanctum 4 / Spatie Data 4 / Pest 4
- 前端：Vue 3 / Inertia 2 / Pinia / Element Plus / Tailwind v4 / Vite 5 / TypeScript
- 数据库：MySQL (生产) / SQLite :memory: (测试)
- 主键：ULID (`HasUlid` trait)
- 租户隔离：`BelongsToTenant` trait + `TenantScope` 全局作用域 + `CurrentTenant` 单例 + `TenantMiddleware` (X-Tenant-Id)

模块布局：

```
app/
  Modules/
    Identity/  - User + Membership + 登录/Me/中间件
    Tenancy/   - Tenant + Store + 平台后台端点
  Support/
    Eloquent/  - HasUlid + BelongsToTenant + TenantScope
    Tenancy/   - CurrentTenant + CurrentMembership 单例
    ModuleServiceProvider.php
  Console/Commands/CoffeeBootstrap.php
```

## 文档

- 设计：`docs/superpowers/specs/2026-05-07-identity-skeleton-design.md`
- 实施计划：`docs/superpowers/plans/2026-05-08-identity-skeleton-implementation.md`
- base.md：`docs/base.md`（设计来源）

## RBAC（首期 minimal）

权限模型按 `docs/superpowers/specs/2026-05-08-rbac-design.md` 实现：

- 4 张表：roles / platform_roles / user_role_bindings / users.platform_role_id
- 2 个 enum：Permission（商户域 6 个）/ PlatformPermission（平台域 6 个）
- 双中间件：`permission:roles.manage` / `platform_permission:platform.tenants.manage`
- 6 个内置预设角色（migrate 时 seed）：

| 表 | code | 用途 |
|---|---|---|
| roles (全局模板) | TenantAdmin | 商户管理员（全部 6 商户权限） |
| roles (全局模板) | StoreManager | 店长（读 + roles.read + users.read） |
| roles (全局模板) | StoreClerk | 店员（读类） |
| platform_roles | PlatformSuperAdmin | 平台超管（全部 6 平台权限） |
| platform_roles | PlatformOps | 平台运营（不含 read-only impersonation） |
| platform_roles | PlatformReadOnly | 仅 read-only impersonation |

**INV 关键**

- INV-A/B：商户 / 平台权限码严格不互通（双 enum + 双 ValidPermissionsRule 校验）
- INV-C/D：PermissionMiddleware 双路径互斥，`is_platform_impersonation` 一票决定
- INV-F：scope=tenant ↔ store_id NULL；scope=store ↔ store_id 非 NULL
- INV-H：is_system=true 角色不可改/删

**dev 数据**：`php artisan db:seed` 后已用 `secret123` 登录的几个账号都已绑预设角色（见 README 速查表）。
