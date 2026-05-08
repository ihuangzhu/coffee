# Coffee 身份骨架设计（base.md §1~§2 / §6.1 首期实现）

- **状态**：Draft，待 PR 评审
- **作者**：Claude（与 lj271263823@gmail.com 协作）
- **日期**：2026-05-07
- **关联文档**：`docs/base.md`（设计来源）、`D:\Projects\Php\service\supermarket`（框架来源）

---

## 1. 目标与非目标

### 1.1 目标

将 supermarket 的"模块化单体 + 多租户基础设施"框架资产移植到 coffee 仓库，并按 base.md §1~§2 / §6.1 的口径实现**最小可跑的多租户身份骨架**：

- Platform / Tenant / Store / User / Membership 五张概念表（base.md §6.1）
- Sanctum 登录 + `tenant` 中间件（X-Tenant-Id header）
- `BelongsToTenant` trait + `TenantScope` 全局作用域 + `CurrentTenant` 请求级单例
- Inertia 登录页 + 切换租户页（最小 UI 验证 happy path）
- artisan CLI 种子命令创建首个 platform admin
- Pest 4 集成测试，覆盖跨租户隔离与 `tenant` 中间件鉴权

### 1.2 非目标（首期显式不做）

| 范围 | base.md 章节 | 推迟原因 |
|---|---|---|
| Role / Permission / role_permission_rel / user_role_rel | §3、§6.2 | RBAC 体量等同首期总量，单独迭代 |
| 数据权限范围 ALL/STORE/SELF/CUSTOM | §5 | 依赖 RBAC，必须后置 |
| `audit_logs` | §8.4 | 依赖 RBAC，敏感操作未上线无内容可审 |
| 套餐 / 计费 / package | §1.1 | SaaS 商业化层，与身份骨架解耦 |
| Tenant 自助注册 | — | 首期所有 tenant 由 platform admin 通过 HTTP 创建 |
| 平台后台 UI / 用户管理 UI / 门店管理 UI | — | curl/Postman 即可演示，UI 二迭代再补 |
| 多语言、定制角色、API 限流（除 login throttle 外） | — | YAGNI |
| 任何 coffee 业务（订单、商品、库存、营销、会员） | — | 用户原话"重新设计业务逻辑"，业务模块独立迭代 |

---

## 2. 架构

### 2.1 技术栈（直接复用 supermarket）

- **后端**：PHP 8.3+、Laravel 13、Sanctum 4（personal access token，非 SPA stateful）、Spatie Data 4、Scramble OpenAPI、Pint
- **测试**：Pest 4 + Pest-Laravel
- **前端**：Vue 3 + Inertia 2 + Pinia + Element Plus + vue-i18n + Tailwind v4 + TypeScript + Vite 5
- **数据库**：生产 MySQL，测试 SQLite（`:memory:`）
- **主键**：ULID（`HasUlid` trait）

### 2.2 模块布局

```
app/
  Modules/
    Identity/                          # 身份域（User + Membership）
      Database/{Migrations,Factories}
      Models/{User.php, Membership.php}
      Http/{Controllers,Middleware,Requests}
      Services/                        # 跨实体编排（首期可能为空）
      Routes/api.php
      IdentityServiceProvider.php
      Tests/                           # Pest 集成测试
    Tenancy/                           # 租户域（Tenant + Store）
      Database/{Migrations,Factories}
      Models/{Tenant.php, Store.php}
      Http/{Controllers,Requests}
      Routes/api.php
      TenancyServiceProvider.php
      Tests/
  Support/
    Eloquent/{HasUlid.php, BelongsToTenant.php, TenantScope.php}
    Tenancy/{CurrentTenant.php, CurrentMembership.php}
    ModuleServiceProvider.php          # 自动注册 Modules/*ServiceProvider
  Console/Commands/CoffeeBootstrap.php # 首装：创建首个 platform admin
```

模块依赖方向：`Tenancy → Identity` 单向；未来业务模块 `→ Tenancy`。**禁止反向依赖**（与 supermarket 同约定）。

### 2.3 中间件管线

```
auth:sanctum  →  tenant  →  (后续: permission / audit)
```

- `auth:sanctum`：Laravel 内置，校验 `Authorization: Bearer <token>`
- `tenant`（首期实现）：
  - 要求 `X-Tenant-Id` header；缺失返回 403 `X-Tenant-Id header required`
  - `is_platform_admin=true` 用户：直接信任 header，注入 `CurrentTenant`、标记 `is_platform_impersonation=true`、`CurrentMembership=null`
  - 普通用户：必须存在 `membership(user_id, tenant_id, status='active')`，否则 403 `no active membership`；通过则注入 `CurrentTenant` + `CurrentMembership`
- `platform_admin`（首期简化版）：仅校验 `user.is_platform_admin === true`；二迭代补 `:perm` 二参数

`platform_admin` 路由不挂 `tenant` 中间件（平台员工创建租户时，租户尚未存在）。

---

## 3. 数据模型（base.md §6.1 严格对齐）

所有表 ULID 主键、`created_at` / `updated_at` 由 Laravel 自动管理。

### 3.1 `tenants`

| 字段 | 类型 | 约束 | 含义 |
|---|---|---|---|
| id | char(26) | PK, ULID | 租户 ID |
| name | varchar(120) | NOT NULL | 租户名（品牌名） |
| status | enum | active / disabled | 租户状态 |

不含 `package_id`（套餐章节非首期）。

### 3.2 `stores`

| 字段 | 类型 | 约束 | 含义 |
|---|---|---|---|
| id | char(26) | PK, ULID | 门店 ID |
| tenant_id | char(26) | FK→tenants, NOT NULL | 所属租户 |
| name | varchar(120) | NOT NULL | 门店名 |
| status | enum | active / disabled | 门店状态 |

索引：`(tenant_id, status)`。
**不含 `code`**（YAGNI；后续按需补门店业务编码）。

### 3.3 `users`

| 字段 | 类型 | 约束 | 含义 |
|---|---|---|---|
| id | char(26) | PK, ULID | 用户 ID |
| name | varchar(120) | NOT NULL | 显示名 |
| phone | varchar(20) | UNIQUE, NOT NULL | 全局登录账号 |
| password | varchar(255) | NOT NULL | hashed cast 自动哈希 |
| status | enum | active / disabled | 用户状态 |
| is_platform_admin | bool | DEFAULT false | 仅 CLI 可置为 true |
| last_login_at | timestamp | nullable | 最近登录时间（登录成功时更新） |

**不含 `tenant_id`**（INV：用户为全局身份，跨租户绑定通过 `memberships`）。
**不含 `email`**（首期登录方式收敛为 phone+password；email 字段未来按需加）。

### 3.4 `memberships`

base.md §2 / §6.1 的"用户归属关系表"。承担"用户在某租户/门店的成员资格"。

| 字段 | 类型 | 约束 | 含义 |
|---|---|---|---|
| id | char(26) | PK, ULID | 关系 ID |
| user_id | char(26) | FK→users, NOT NULL | 用户 |
| tenant_id | char(26) | FK→tenants, NOT NULL | 租户 |
| store_id | char(26) | FK→stores, nullable | NULL=租户级；非 NULL=门店级 |
| status | enum | active / left | 当前状态 |
| joined_at | timestamp | NOT NULL | 加入时间 |

索引：`(user_id)` `(tenant_id, store_id)`。
**唯一性**：`(user_id, tenant_id, store_id) where status='active'` 由应用层 + Pest 测试保证；首期不做 DB 部分唯一索引（避免 SQLite/MySQL 语法分歧）。二迭代视并发场景再加。

### 3.5 `personal_access_tokens`

Sanctum 内置 migration（直接 `php artisan vendor:publish`，零改动）。

---

## 4. HTTP API（首期 9 端点）

| # | 方法 | 路径 | 中间件 | 说明 |
|---|---|---|---|---|
| 1 | POST | `/api/auth/login` | （无） | phone + password → 返回 `{ token, user: {id, phone, name, is_platform_admin} }` |
| 2 | POST | `/api/auth/logout` | `auth:sanctum` | 撤销当前 token |
| 3 | GET | `/api/me` | `auth:sanctum` | 当前 user 基础信息 |
| 4 | GET | `/api/me/memberships` | `auth:sanctum` | 列出当前 user 的全部 active `memberships`（前端切租户用）|
| 5 | GET | `/api/tenants/current` | `auth:sanctum, tenant` | 当前 tenant 详情（验证 `X-Tenant-Id` 解析成功）|
| 6 | GET | `/api/stores` | `auth:sanctum, tenant` | 列当前 tenant 下的 stores |
| 7 | POST | `/api/platform/tenants` | `auth:sanctum, platform_admin` | 创建租户（payload: name）|
| 8 | POST | `/api/platform/tenants/{id}/stores` | `auth:sanctum, platform_admin` | 给租户创建门店（payload: name）|
| 9 | POST | `/api/platform/tenants/{id}/users` | `auth:sanctum, platform_admin` | 创建用户并绑定到 tenant（payload: name, phone, password, store_id?）|

请求/响应 DTO 用 Spatie Data。所有响应 4xx 用 Laravel 默认 JSON 错误结构。

### 4.1 登录限流

`/api/auth/login` 挂 `throttle:5,1`（5 次/分钟 / IP）—— 仿 supermarket。

---

## 5. 多租户基础设施

### 5.1 `Support/Tenancy/CurrentTenant`

请求生命周期单例。`set(?string $id)` / `id(): ?string` / `require(): string`（require 失败抛 `RuntimeException`）。由 `TenantMiddleware` 在请求开始时通过 `set()` 注入；测试可直接 `app(CurrentTenant::class)->set(...)`。

### 5.2 `Support/Tenancy/CurrentMembership`

同上结构；持有当前 `Membership` 模型实例（普通用户）或 `null`（平台员工伪装态）。

### 5.3 `Support/Eloquent/BelongsToTenant` trait

业务模型挂此 trait 即获得：
1. `bootBelongsToTenant`：`addGlobalScope(new TenantScope)`，所有查询自动追加 `WHERE tenant_id = <CurrentTenant>`；`CurrentTenant` 为 null 时**不追加**（兼容登录前查询）
2. `creating` 监听：写入时若未显式赋 `tenant_id`，自动注入 `CurrentTenant::require()`

`Tenant` 模型本身**不挂**此 trait（它是租户根）。`User` 也**不挂**（全局身份）。`Store`、`Membership` 都挂。

> **`Membership` + `withoutGlobalScopes()` 模式**：`Membership` 挂 trait 是为了业务流（如查"当前租户下的成员"）默认安全，但两类查询必须显式绕过全局作用域：
>
> 1. `tenant` 中间件解析时：`Membership::query()->withoutGlobalScopes()->where('user_id', $user->id)->where('tenant_id', $merchantId)->where('status', 'active')->first()` —— 此时 `CurrentTenant` 单例可能持有上一请求的残留值，必须显式去全局作用域
> 2. `/api/me/memberships`：跨租户列出 user 的所有绑定，必须 `withoutGlobalScopes()`
>
> 此模式与 supermarket 完全一致，`TenantMiddleware` 直接 1:1 移植即可。

### 5.4 `Support/Eloquent/HasUlid` trait

ULID 主键自动生成。从 supermarket 直接 1:1 拷贝。

---

## 6. 前端（Inertia 最小骨架）

### 6.1 页面

- **`/login`**：phone + password 表单 → POST `/api/auth/login` → 拿 token 写 localStorage（axios 拦截器后续读出加 Authorization header）→ 跳转 `/select-tenant`
- **`/select-tenant`**：调 `/api/me/memberships` 列出可进入的 tenant 卡片；点选则把 `tenant_id` 存 Pinia + localStorage、之后 axios 拦截器自动塞 `X-Tenant-Id` header；跳转 `/`
- **`/`**：仅作 placeholder，显示 `欢迎，{user.name} @ {tenant.name}` + "切换租户"按钮（清 tenant_id，跳回 `/select-tenant`）

### 6.2 axios 全局拦截器

```ts
axios.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  const tenantId = localStorage.getItem('tenant_id');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  if (tenantId) config.headers['X-Tenant-Id'] = tenantId;
  return config;
});
```

### 6.3 不做

dashboard、用户/门店/租户的 CRUD UI、平台后台 UI、错误页、加载骨架、暗色主题。仅满足"登录 → 选租户 → 看到当前租户名"的端到端 happy path。

---

## 7. 首装 CLI

`php artisan coffee:bootstrap --phone=... --password=... --name=...`

行为：
1. 检查是否已有 `is_platform_admin=true` 用户；有则中止（防误执行二次注入）
2. 创建一个 `is_platform_admin=true` 用户
3. 输出登录提示

不创建任何 tenant —— 平台员工登录后通过 `/api/platform/tenants` 创建首个 tenant。

---

## 8. 测试策略（Pest 4）

每个 HTTP 端点至少 1 个 happy path + 1 个权限被拒 case。关键不变量额外测：

### 8.1 跨租户隔离（最关键）

```text
测试：BelongsToTenant 阻止跨租户读取
  given: tenant A 下有 store S_A；tenant B 下有 store S_B
  when:  CurrentTenant=A 调 Store::all()
  then:  仅返回 S_A，不含 S_B
```

```text
测试：BelongsToTenant 阻止跨租户写入
  given: CurrentTenant=A
  when:  Store::create(['name' => 'X'])
  then:  写入的 store.tenant_id === A，且 CurrentTenant=B 时查不到
```

### 8.2 `tenant` 中间件鉴权

```text
- 缺 X-Tenant-Id → 403
- X-Tenant-Id 是 user 没有 active 绑定的 tenant → 403
- X-Tenant-Id 是 user 有 active 绑定的 tenant → 200, CurrentTenant 正确
- is_platform_admin=true 任意 X-Tenant-Id → 200, is_platform_impersonation=true
- membership.status=left → 403
```

### 8.3 登录

```text
- 正确 phone+password → 200 + token
- 错误 password → 422（ValidationException，phone 字段统一报"账号或密码错误"）
- phone 不存在 → 422（同上，避免账号枚举）
- 限流：6 次错误连续请求 → 第 6 次 429
```

### 8.4 平台员工创建链

```text
- platform admin 创建 tenant → 200，DB 插入 tenants 行
- platform admin 创建 store → store.tenant_id 正确
- platform admin 创建 user + 绑定 → memberships 行存在 status=active
- 非 platform admin 调 /api/platform/* → 403
```

### 8.5 测试基础设施

- 数据库使用 SQLite `:memory:`（`phpunit.xml` 配置 `DB_CONNECTION=sqlite, DB_DATABASE=:memory:`）
- 每个测试 `RefreshDatabase` trait
- `Queue::fake()` 防 sync 队列污染（即使首期没用 Queue，提前装好）
- 测试不使用 mock，全部真 DB 查询（与 supermarket 约定一致）

---

## 9. 移植清单（哪些文件直接从 supermarket 复制 / 改名 / 重写）

### 9.1 直接拷贝（仅改 namespace 和租户相关命名）

- `composer.json` —— 删 `inertiajs/inertia-laravel` 之外的业务相关依赖（首期不需要 `dedoc/scramble` 也行，但保留无成本，留）
- `package.json`、`pint.json`、`phpunit.xml`、`vite.config.ts`、`tailwind.config.js`、`tsconfig.json`、`eslint.config.js`、`.editorconfig`、`.gitignore`、`.gitattributes`、`.npmrc`
- `app/Support/Eloquent/HasUlid.php`
- `app/Support/Exceptions/`（沿用框架级异常基类）
- `app/Support/ModuleServiceProvider.php`（自动发现 `Modules/*ServiceProvider`）
- `app/Support/SensitiveFieldFilter.php`
- `bootstrap/app.php`（中间件别名 + 模块发现注册）
- `config/sanctum.php`、`config/auth.php`（如有改动）
- artisan stub、`.env.example`、`README.md` 模板

### 9.2 改名后拷贝

| supermarket 路径 | coffee 路径 | 改动 |
|---|---|---|
| `app/Support/Eloquent/BelongsToMerchant.php` | `app/Support/Eloquent/BelongsToTenant.php` | 全文 `Merchant`→`Tenant`，`merchant_id`→`tenant_id` |
| `app/Support/Eloquent/MerchantScope.php` | `app/Support/Eloquent/TenantScope.php` | 同上 |
| `app/Support/Tenancy/CurrentMerchant.php` | `app/Support/Tenancy/CurrentTenant.php` | 同上 |
| `app/Support/Tenancy/CurrentMembership.php` | 不变 | 字段类型从 `MerchantUser` 改为 `Membership` |
| `app/Modules/Auth/Http/Middleware/TenantMiddleware.php` | `app/Modules/Identity/Http/Middleware/TenantMiddleware.php` | 改 namespace；查询 `MerchantUser` → 查询 `Membership`；header 名 `X-Merchant-Id` → `X-Tenant-Id` |
| `app/Modules/Auth/Http/Controllers/AuthController.php` + `LoginAction` + `LoginRequest` + `LoginData` | `app/Modules/Identity/...` 同结构 | 改 namespace；其它逻辑保留 |
| `app/Modules/Auth/Http/Controllers/MeController.php` | `app/Modules/Identity/Http/Controllers/MeController.php` | 改 namespace；`memberships()` 关系改向 `Membership` |

### 9.3 全部重写（按 base.md §6.1）

- `app/Modules/Identity/Models/User.php` `Membership.php`
- `app/Modules/Identity/Database/Migrations/*`（5 张表，按 §3 字段定义）
- `app/Modules/Identity/Database/Factories/*`
- `app/Modules/Tenancy/Models/Tenant.php` `Store.php`
- `app/Modules/Tenancy/Database/Migrations/*`
- `app/Modules/Tenancy/Database/Factories/*`
- `app/Modules/Tenancy/Http/Controllers/*`（PlatformTenantController、PlatformStoreController、PlatformUserController、StoreController）
- `app/Console/Commands/CoffeeBootstrap.php`
- 前端 `resources/js/Pages/{Login,SelectTenant,Home}.vue` + `Layouts/AppLayout.vue` + axios 拦截器配置 + Pinia tenant store

### 9.4 不移植（supermarket 业务模块）

`Modules/{Goods,Inventory,Order,Payment,Promotion,Purchasing,Aggregator,Activity}` 全部不动。其中 `Modules/Auth` 中的 `Permission` 枚举、`Role` 模型、`PlatformRole` 模型、`StoreUser` 模型、`AuditLog` 模型、`PermissionMiddleware`、`AuditMiddleware`、`PlatformAdminMiddleware`、`Platform` controllers 等都**不移植**，留给二迭代 RBAC 时按 base.md §3 重新设计。

---

## 10. 风险与已知决策的代价

| 决策 | 代价 | 缓解 |
|---|---|---|
| 首期不做 RBAC，所有 platform 端点仅靠 `is_platform_admin` 布尔判定 | 二迭代加 RBAC 时，`platform_admin` 中间件需扩展为 `platform_admin:perm` 形态，但路由签名兼容（多参数可选） | 中间件接口预留二参数位 |
| 首期不做 audit log | 平台敏感操作（创建租户、创建门店、发用户）无审计 | 二迭代 §8.4 时按 supermarket `AuditMiddleware` 模式补 |
| `memberships` 唯一性走应用层 | 高并发下可能写入两条 active 行 | 首期 platform admin 单线程操作量极小；二迭代加 DB 约束 |
| 用户登录账号仅 phone | 海外/无中国手机用户无法登录 | 首期目标用户为国内咖啡店主，phone 足够；后续加 email 时只是 `users` 表加一个 nullable unique 列 |
| 不做前端错误处理/加载状态 | 体验粗糙 | 首期是 happy path 演示，错误用 alert 即可 |

---

## 11. 验收标准

实现完成的判定（每条都需有 Pest 测试 / 手动验证证据）：

1. `composer install && npm install && php artisan migrate && php artisan coffee:bootstrap --phone=... --password=...` 一键起项目
2. 平台员工登录 → curl `/api/platform/tenants` 创建租户 → curl `/api/platform/tenants/{id}/users` 创建用户并绑定 → 该用户登录 → 选租户 → 看到自己的租户名
3. 用户 A 在租户 T1，用户 B 在租户 T2；用户 A 用 X-Tenant-Id=T2 调 `/api/stores` 收到 403
4. 平台员工任意 X-Tenant-Id 调 `/api/stores` 返回该租户的 stores
5. 整套 Pest 测试 100% pass，覆盖 §8 全部场景
6. `npm run build` + `vue-tsc --noEmit` 通过；`vendor/bin/pint --test` 通过
