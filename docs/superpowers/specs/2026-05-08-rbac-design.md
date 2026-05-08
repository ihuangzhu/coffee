# RBAC 模块设计（首期 minimal）

**Date:** 2026-05-08
**Status:** Approved (brainstorm)
**Scope reference:** `docs/base.md` §3 + §6.2，`docs/rbac.md`，supermarket `app/Modules/Auth`

---

## 1. 目标与范围

**目标**：在 Identity / Tenancy 骨架之上，落地多租户带作用域的 RBAC 最小可用版本，让商户管理员可以创建角色、给员工分配角色，让 PermissionMiddleware 对每个被保护的端点进行 hard 鉴权。

**范围**：
- 4 张表：`roles` / `platform_roles` / `user_role_bindings` + `users.platform_role_id` 列
- 2 个 PHP enum：`Permission`（商户域）/ `PlatformPermission`（平台域），首期共 12 个权限码
- 2 个 Form Request 校验规则：`ValidPermissionsRule` / `ValidPlatformPermissionsRule`
- 1 个 PermissionMiddleware（双路径：商户员工权限 + 平台员工 impersonation 分级）+ 1 个 PlatformPermissionMiddleware
- TenantMiddleware 增量改造：注入 `platform_role` / `current_role_bindings` / `effective_permissions` 到 request attributes
- 13 个 API 端点：8 商户域 + 5 平台域
- 6 个内置预设角色（is_system=true，迁移时 seed）
- 全套 Pest 测试（单元 + 中间件 + 端点 + 集成）

**首期不做（明确划线）**：
- 数据权限（data_scope）—— 留待具体业务模块（Order / Inventory）各自定义
- 角色有效期（effective_from/to）
- 可授权角色集（grantable_roles，授权传递约束）
- "基于模板克隆为本租户角色"接口
- 审计日志（AuditMiddleware / audit_logs 表）
- 雇员邀请 / 离职 / 密码改期等 supermarket 衍生功能

---

## 2. 方案对比与决策

读完 `docs/rbac.md`（理想设计）+ supermarket Auth（已实现）后选择**折衷方案 D**：

| 维度 | rbac.md | supermarket | 折衷方案 D（本 spec） |
|---|---|---|---|
| 权限来源 | DB 表 `permission` | PHP enum | **PHP enum**（单一来源，IDE 检索友好） |
| 角色权限关系 | `role_permission_rel` 关联表 | role.permissions JSON | **JSON 数组**（一行 SELECT 取所有） |
| 角色作用域隔离 | 单 `role` 表 + `scope_type` 字段 | `roles` + `platform_roles` 双表 | **双表硬隔离**（INV-14） |
| 用户角色绑定 | 独立 `user_role_binding` 表 | `memberships.role_id` | **独立 `user_role_bindings` 表**（支持一人多角色） |
| 数据权限 | role.data_scope_type | 无 | **不做**（YAGNI 第一期） |
| 可授权角色集 | grantable_roles | 无 | **不做** |

**决策依据**：
- enum + JSON：稳定 + 性能好 + 编译期约束（参考 supermarket 经验）
- 独立绑定表：避免将来加多角色时迁数据（参考 rbac.md 前瞻性）
- 双表硬隔离 platform / merchant 权限：避免权限码越境的根本性 bug（INV-14）
- 不做数据权限：base.md / rbac.md 都比较抽象，等具体业务模块（Goods/Order）来时再设计反而更准

---

## 3. 数据模型

### 3.1 `roles`（商户域角色）

```text
id            CHAR(26) PK              -- ULID
tenant_id     CHAR(26) nullable        -- NULL = 系统预置全局模板；非 NULL = 商户专属
name          VARCHAR(120)
code          VARCHAR(60)              -- 系统角色用稳定 code (TenantAdmin / StoreManager / StoreClerk)
scope         ENUM('tenant','store')   -- 该角色作用层级
permissions   JSON                     -- string[] 仅含 Permission enum 合法值
is_system     BOOLEAN default false    -- true 内置不可删
created_at, updated_at

INDEX (tenant_id)
INDEX (code)
-- 不加 UNIQUE(tenant_id, code) —— MySQL InnoDB 中 UNIQUE 视 NULL ≠ NULL，
-- 多份 (NULL, 'TenantAdmin') 不被拒；改由 Service 层 + seeder firstOrCreate 保证唯一

FK tenant_id → tenants.id  cascadeOnDelete  (NULL 列允许)
```

**注意**：
- 不挂 `BelongsToTenant` trait —— `tenant_id IS NULL` 的全局模板需跨租户可见，由 Service 层显式过滤即可。
- `scope=tenant` 角色绑定时 `store_id` 必须 NULL；`scope=store` 必须非 NULL。这条约束在 Form Request 层校验，DB 层不强制（让数据迁移更灵活）。

### 3.2 `platform_roles`（平台域角色，与 `roles` 完全独立 / INV-14）

```text
id            CHAR(26) PK
name          VARCHAR(120)
code          VARCHAR(60) UNIQUE       -- PlatformSuperAdmin / PlatformOps / PlatformReadOnly
permissions   JSON                     -- string[] 仅含 PlatformPermission enum 合法值
is_system     BOOLEAN default false
created_at, updated_at
```

**为何独立表**：强制 `platform.*` 权限码不可越境到商户角色，反之亦然。`ValidPermissionsRule` / `ValidPlatformPermissionsRule` 双向校验；写入即拒。

### 3.3 `user_role_bindings`

```text
id          CHAR(26) PK
user_id     CHAR(26)  FK→users.id           cascadeOnDelete
role_id     CHAR(26)  FK→roles.id           restrictOnDelete  -- 角色被引用不能删
tenant_id   CHAR(26)  FK→tenants.id         cascadeOnDelete   -- 必填
store_id    CHAR(26) nullable FK→stores.id  nullOnDelete      -- scope=store 时非 NULL
status      ENUM('active','revoked')        default 'active'
granted_by  CHAR(26) nullable                                   -- 哪个 user 授的，撤了可追溯
granted_at  TIMESTAMP
created_at, updated_at

INDEX (user_id, tenant_id)
INDEX (tenant_id, store_id)

-- 应用层而非 DB 层去重（NULL store_id 在 SQL UNIQUE 中互不等于）：
-- 创建/复活时按 (user_id, role_id, tenant_id, COALESCE(store_id, '')) 检查 active 重复
```

**挂 `BelongsToTenant`** —— 默认查询自动带 tenant 隔离。跨租户场景显式 `withoutGlobalScopes()`。

**为何独立表（不放 `memberships.role_id`）**：
- 一个 user 在一个 tenant 内可持有 N 个角色（如店长 @ S1 + 店员 @ S2），权限取并集
- 撤销不丢历史：`status=revoked` 留痕
- 与 `memberships`（"我属于这家组织"）正交

### 3.4 `users.platform_role_id` 列

```text
ALTER TABLE users ADD platform_role_id CHAR(26) nullable
  REFERENCES platform_roles(id) nullOnDelete;
```

**约束**：仅 `is_platform_admin=true` 的 user 可持有 `platform_role_id`。Service 层校验。
**为什么不也独立成表**：平台员工总数极少（个位数到两位数），1:1 够用；后续真要多平台角色再迁。

---

## 4. 权限 Enum 与预设角色

### 4.1 `Permission`（商户域，6 个）

```php
namespace App\Modules\Authorization\Enums;

enum Permission: string
{
    case RolesRead       = 'roles.read';
    case RolesManage     = 'roles.manage';
    case UsersRead       = 'users.read';
    case UsersAssignRole = 'users.assign-role';
    case TenantRead      = 'tenant.read';
    case StoresRead      = 'stores.read';
}
```

后续 Goods / Order / Inventory 各自 PR 在此 enum 追加 cases。命名规范：`{resource}.{action}`，禁止 `platform.` 前缀。

### 4.2 `PlatformPermission`（平台域，6 个）

```php
enum PlatformPermission: string
{
    case ImpersonateFull     = 'platform.impersonate.full';      // 任意 HTTP 方法放行
    case ImpersonateReadOnly = 'platform.impersonate.read-only'; // 仅 GET / HEAD 放行
    case TenantsManage       = 'platform.tenants.manage';
    case StoresManage        = 'platform.stores.manage';
    case UsersManage         = 'platform.users.manage';
    case PlatformRolesManage = 'platform.roles.manage';
}
```

**INV-14 严格不互通**：所有 case 必须 `platform.` 前缀。`ValidPermissionsRule`（商户）拒绝任何 `platform.*`；`ValidPlatformPermissionsRule`（平台）拒绝任何不带 `platform.` 前缀。

### 4.3 内置预设角色（迁移时 seed，is_system=true）

| 表 | code | scope | tenant_id | permissions |
|---|---|---|---|---|
| roles | `TenantAdmin` | tenant | NULL（全局模板） | 全部 6 个 Permission |
| roles | `StoreManager` | store | NULL（全局模板） | RolesRead, UsersRead, TenantRead, StoresRead |
| roles | `StoreClerk` | store | NULL（全局模板） | TenantRead, StoresRead |
| platform_roles | `PlatformSuperAdmin` | — | — | 全部 6 个 PlatformPermission |
| platform_roles | `PlatformOps` | — | — | ImpersonateFull, TenantsManage, StoresManage, UsersManage |
| platform_roles | `PlatformReadOnly` | — | — | ImpersonateReadOnly |

商户管理员可基于全局模板直接绑定到员工，或自建租户专属角色（`tenant_id = X-Tenant-Id`，`is_system=false`）。

---

## 5. 中间件 / 解析链

### 5.1 `TenantMiddleware`（已有，增量改造）

新增注入 request attributes：

| key | 普通员工 | 平台员工伪装态 |
|---|---|---|
| `platform_role` | NULL | PlatformRole 实例（来自 users.platform_role_id 关联） |
| `current_role_bindings` | UserRoleBinding[] (跨该 tenant 全部 active) | NULL |
| `effective_permissions` | string[] (所有 binding.role.permissions 并集) | NULL |
| `is_platform_impersonation` | false | true（已在首期实现） |

**取并集语义**：一个 user 在当前 tenant 内有多个 active binding 时，`effective_permissions` = 所有 role.permissions 数组的并集去重。

**数据范围处理**：首期不做。具体业务模块如需"店长只看 S1 + S2"的数据隔离，自行从 `current_role_bindings` 派生 `accessible_store_ids` —— 不在本 spec 范围。

### 5.2 `PermissionMiddleware`（新增，`app/Modules/Authorization/Http/Middleware/`）

挂在 `tenant` 之后，按权限 key 校验：

```php
Route::middleware(['auth:sanctum', 'tenant', 'permission:roles.manage'])
    ->post('/api/roles', [RoleController::class, 'store']);
```

**双路径逻辑（伪代码）**

```text
if request.attributes['is_platform_impersonation'] === true:
    pp = request.attributes['platform_role']?.permissions ?? []
    if 'platform.impersonate.full' in pp:
        next()  # 任意方法放行
    elif 'platform.impersonate.read-only' in pp and method in ['GET','HEAD']:
        next()
    else:
        403 BusinessException('platform.impersonation.method-not-allowed')
else:  # 普通商户员工
    eff = request.attributes['effective_permissions'] ?? []
    if permissionKey not in eff:
        403 BusinessException('permission.missing', message="missing permission: {key}")
    next()
```

**关键不变量**：
- 平台员工伪装路径**绝不**消费 user_role_bindings（平台员工没有商户角色）
- 商户员工路径**绝不**消费 platform_role
- 双路径完全互斥，由 `is_platform_impersonation` 一票决定

### 5.3 `PlatformPermissionMiddleware`（新增）

```php
Route::middleware(['auth:sanctum', 'platform_admin', 'platform_permission:platform.tenants.manage'])
    ->post('/api/platform/tenants', ...);
```

不依赖 X-Tenant-Id（平台后台不强制选租户）。直接从 `auth()->user()->platformRole` 关系读取 `permissions`，校验包含 `permissionKey`。无 platform_role 或缺权限 → 403 `platform.permission.missing`。

---

## 6. API 端点

### 6.1 商户域（`auth:sanctum, tenant, permission:*`）

| # | 方法 | 路径 | permission key | 说明 |
|---|---|---|---|---|
| 1 | GET | `/api/roles` | `roles.read` | 列本租户角色 + 全局模板 |
| 2 | POST | `/api/roles` | `roles.manage` | 商户自建角色（强制 tenant_id = X-Tenant-Id，is_system=false） |
| 3 | PATCH | `/api/roles/{id}` | `roles.manage` | 改 name/permissions；is_system=true 拒 |
| 4 | DELETE | `/api/roles/{id}` | `roles.manage` | 被 active binding 引用 → 409 |
| 5 | GET | `/api/users` | `users.read` | 列本租户员工（join memberships, 不含 left） |
| 6 | POST | `/api/users/{userId}/role-bindings` | `users.assign-role` | 给员工绑角色（请求体: role_id + 可选 store_id） |
| 7 | DELETE | `/api/role-bindings/{id}` | `users.assign-role` | 撤销（status=revoked，留痕） |
| 8 | GET | `/api/me/permissions` | （仅 `auth:sanctum, tenant`，无 permission） | 返回当前 user 的 effective_permissions / platform_permissions / is_platform_impersonation |

**关键约束**：
- #2 创建：`scope ∈ {tenant, store}`；`permissions[]` 经 `ValidPermissionsRule`（拒绝 `platform.*`）
- #6 创建绑定：
  - `role.scope=tenant` → 请求体 `store_id` 必须缺省或 NULL
  - `role.scope=store` → `store_id` 必填，且必须属于 X-Tenant-Id 对应租户（防越权绑别租户的店）
  - role 必须属于 X-Tenant-Id 或 `tenant_id IS NULL` 全局模板
- #4 删角色：SQL `EXISTS` 检查 active binding；存在则 409 + 错误码 `role.in-use`，不级联

### 6.2 平台域（`auth:sanctum, platform_admin, platform_permission:*`）

| # | 方法 | 路径 | platform_permission key |
|---|---|---|---|
| 9  | GET    | `/api/platform/roles`           | `platform.roles.manage` |
| 10 | POST   | `/api/platform/roles`           | `platform.roles.manage` |
| 11 | PATCH  | `/api/platform/roles/{id}`      | `platform.roles.manage` |
| 12 | DELETE | `/api/platform/roles/{id}`      | `platform.roles.manage` |
| 13 | PATCH  | `/api/platform/users/{userId}/platform-role` | `platform.roles.manage` |

**关键约束**：
- #10/#11 ：`permissions[]` 经 `ValidPlatformPermissionsRule`（仅 `PlatformPermission` enum，强制 `platform.*` 前缀）
- #13 ：先 SELECT 验证目标 user `is_platform_admin=true`，否则 422 `platform-role.target-not-admin`

### 6.3 现有平台后台端点追加权限保护（**修改**，非新增）

`/api/platform/tenants` / `/api/platform/tenants/{id}/stores` / `/api/platform/tenants/{id}/users`：在路由声明里追加 `platform_permission:platform.tenants.manage` / `:platform.stores.manage` / `:platform.users.manage`。

`PlatformSuperAdmin` 持有所有 platform permission，等价当前"`is_platform_admin=true` 全开"语义，向后兼容。其他细分平台角色（`PlatformReadOnly` 等）将受细粒度限制。

---

## 7. 错误处理

| 场景 | HTTP | code | 说明 |
|---|---|---|---|
| 商户员工无对应权限 | 403 | `permission.missing` | message: `missing permission: {key}` |
| 平台员工伪装非允许方法 | 403 | `platform.impersonation.method-not-allowed` | ReadOnly 调 POST 时触发 |
| 平台员工无对应平台权限 | 403 | `platform.permission.missing` | platform_permission middleware |
| 写入角色含跨域权限码 | 422 | `permission.cross-domain` | roles.permissions 含 platform.*，或反之 |
| 角色被引用不能删 | 409 | `role.in-use` | `details.binding_count` |
| 绑定 store 不属本租户 | 422 | `binding.store-not-in-tenant` | 防越权 |
| 绑 store 级角色但未传 store_id | 422 | `binding.store-id-required` | |
| 绑 tenant 级角色却传了 store_id | 422 | `binding.store-id-not-allowed` | |
| 重复绑相同 (user, role, tenant, store) active | 200 | — | 幂等返回已存在 binding；status=revoked 时复活为 active |
| 给非 platform_admin 设 platform_role | 422 | `platform-role.target-not-admin` | |
| 改/删 is_system=true 的角色 | 403 | `role.system-locked` | |

错误响应统一沿用现有 `BusinessException` 渲染管道（`bootstrap/app.php`）：`{ code, message, details }`。所有 `code` 加 i18n key（`lang/zh-CN/errors.php`）。

---

## 8. 测试场景

测试基础设施沿用现状：SQLite `:memory:` + RefreshDatabase + Pest，纯真 DB 查询，无 mock。

### 8.1 单元测试

```text
- ValidPermissionsRule 拒绝 'platform.*'
- ValidPermissionsRule 拒绝拼写错误的 code
- ValidPermissionsRule 接受合法 enum 值
- ValidPermissionsRule 接受空数组
- ValidPlatformPermissionsRule 对称用例
- Permission enum 闭包性测试（防 PR 偶然新增异常前缀）
```

### 8.2 中间件测试

```text
PermissionMiddleware:
- platform_admin + ImpersonateFull → 任意方法放行
- platform_admin + ImpersonateReadOnly + GET → 放行
- platform_admin + ImpersonateReadOnly + POST → 403 platform.impersonation.method-not-allowed
- platform_admin 无 platform_role → 403
- 普通员工 has 'roles.read' + GET /roles → 200
- 普通员工 lacks 'roles.manage' + POST /roles → 403 permission.missing
- 普通员工有多个 active binding，effective_permissions 取并集
- user_role_binding.status=revoked 不计入 effective_permissions

PlatformPermissionMiddleware:
- has platform_role + 含目标权限 → 通过
- has platform_role + 不含目标权限 → 403
- platform_role_id IS NULL → 403
```

### 8.3 端点测试

```text
商户角色 CRUD:
- 列出本租户角色 + 全局模板（is_system 模板可见）
- 创建角色：scope/permissions 校验
- A 租户不能读到 B 租户的自建角色（tenant 隔离）
- 角色被引用 → 409 role.in-use
- 写入 role.permissions 含 'platform.*' → 422 permission.cross-domain
- 改/删 is_system=true → 403 role.system-locked

绑定 CRUD:
- scope=tenant 角色 + 请求体含 store_id → 422 store-id-not-allowed
- scope=store 角色 + 请求体缺 store_id → 422 store-id-required
- 绑别租户的 store_id → 422 store-not-in-tenant
- 重复绑同组合 → 200 幂等
- revoked binding 重新绑定 → 复活为 active

GET /api/me/permissions:
- 普通员工返回 effective_permissions 扁平列表，is_platform_impersonation=false
- 平台员工伪装态返回 platform_permissions（+ is_platform_impersonation=true），不含 effective_permissions
- 跨租户切 X-Tenant-Id → effective_permissions 跟随当前租户的 binding 重新解析

平台角色 CRUD（对称商户域）:
- 写入 platform_roles.permissions 缺 'platform.' 前缀 → 422
- 给非 platform_admin user 设 platform_role → 422 target-not-admin
```

### 8.4 集成测试（`tests/Integration/Authorization/`）

```text
- 角色撤回链：U 在 T1 是 TenantAdmin → POST /api/roles 200 → DELETE binding (status=revoked) → 同 token 再 POST → 403 permission.missing
- 平台员工降级：PlatformSuperAdmin → 改成 PlatformReadOnly → 之前能写的 /api/platform/tenants 现在 403
- 多角色合并：U 同时绑 StoreManager@S1 + StoreClerk@S2，effective_permissions 是两个角色 permissions 的去重并集
```

---

## 9. 文件结构（新建/修改清单）

### 新建

```text
app/Modules/Authorization/
  AuthorizationServiceProvider.php
  Enums/
    Permission.php
    PlatformPermission.php
  Models/
    Role.php
    PlatformRole.php
    UserRoleBinding.php
  Database/
    Factories/
      RoleFactory.php
      PlatformRoleFactory.php
      UserRoleBindingFactory.php
    Migrations/
      2026_05_08_000050_create_roles_table.php
      2026_05_08_000051_create_platform_roles_table.php
      2026_05_08_000052_create_user_role_bindings_table.php
      2026_05_08_000053_alter_users_add_platform_role_id.php
      2026_05_08_000054_seed_system_roles.php   -- 用 migration 而非 seeder，因为 is_system 是骨架不是 fixture
  Http/
    Controllers/
      RoleController.php
      RoleBindingController.php
      MePermissionsController.php
      Platform/
        PlatformRoleController.php
        UserPlatformRoleController.php
    Middleware/
      PermissionMiddleware.php
      PlatformPermissionMiddleware.php
    Requests/
      StoreRoleRequest.php
      UpdateRoleRequest.php
      CreateRoleBindingRequest.php
      StorePlatformRoleRequest.php
      UpdatePlatformRoleRequest.php
  Rules/
    ValidPermissionsRule.php
    ValidPlatformPermissionsRule.php
  Services/
    PermissionResolver.php  -- 取并集逻辑独立成类，便于单测
  Routes/
    api.php
  Tests/
    Unit/
      ValidPermissionsRuleTest.php
      ValidPlatformPermissionsRuleTest.php
      PermissionEnumClosureTest.php
      PermissionResolverTest.php
    Feature/
      RoleEndpointsTest.php
      RoleBindingEndpointsTest.php
      PlatformRoleEndpointsTest.php
      MePermissionsEndpointTest.php
      PermissionMiddlewareTest.php
      PlatformPermissionMiddlewareTest.php

tests/Integration/Authorization/
  RoleRevokeChainTest.php
  PlatformRoleDowngradeTest.php
  MultiBindingMergeTest.php

database/seeders/
  RbacSkeletonSeeder.php  -- dev fixture：给 IdentitySkeletonSeeder 创建的用户分配预设角色
```

### 修改

```text
app/Modules/Identity/Http/Middleware/TenantMiddleware.php
  -- 注入 platform_role / current_role_bindings / effective_permissions

app/Modules/Identity/Models/User.php
  -- 加 platformRole() 关系；加 roleBindings() 关系（hasMany UserRoleBinding）

bootstrap/app.php
  -- 注册 'permission' / 'platform_permission' 中间件别名

database/seeders/DatabaseSeeder.php
  -- 加入 RbacSkeletonSeeder

bootstrap/providers.php
  -- 注册 AuthorizationServiceProvider (Laravel 13 provider 注册位置)

app/Modules/Tenancy/Routes/api.php
  -- 现有 /api/platform/tenants /api/platform/tenants/{id}/stores
     /api/platform/tenants/{id}/users 三个端点追加
     'platform_permission:platform.tenants.manage' /
     ':platform.stores.manage' / ':platform.users.manage' 中间件

lang/zh-CN/errors.php
  -- 加 11 个新错误码翻译
```

---

## 10. 不变量清单（INV）

记录设计中必须坚守的不变量，便于代码 review 时确认：

| INV | 描述 |
|---|---|
| INV-A | `Permission` enum 所有 case 必须无 `platform.` 前缀；`PlatformPermission` enum 所有 case 必须有 `platform.` 前缀 |
| INV-B | `roles.permissions` 写入仅接受 `Permission::cases()` 的 value；`platform_roles.permissions` 写入仅接受 `PlatformPermission::cases()` 的 value（双向校验） |
| INV-C | `is_platform_impersonation=true` 的请求**绝不**消费 `current_role_bindings` / `effective_permissions` |
| INV-D | `is_platform_impersonation=false` 的请求**绝不**消费 `platform_role` |
| INV-E | `user_role_bindings.tenant_id` 必填；写入时严格匹配 X-Tenant-Id（除非来自平台后台路径） |
| INV-F | `user_role_bindings.store_id` 与 `roles.scope` 一致性：scope=tenant ↔ store_id NULL；scope=store ↔ store_id 非 NULL |
| INV-G | `users.platform_role_id` 仅当 `is_platform_admin=true` 时可非 NULL |
| INV-H | `is_system=true` 的角色不可被 PATCH / DELETE（无论商户域还是平台域） |

---

## 11. 后续迭代（不在本期）

- **数据权限**：等 Order / Inventory 模块需要时引入 `accessible_store_ids` 派生 + Eloquent 全局 scope
- **审计日志**：新建 `AuditMiddleware` + `audit_logs` 表（参考 supermarket 实现）
- **角色有效期**：UserRoleBinding 加 effective_from / effective_to 列
- **可授权角色集**：roles 加 grantable_role_ids 列，校验"不能授超过自己的权限"
- **基于模板克隆**：`POST /api/roles/clone-template` 端点
- **门店级角色管理**：现行设计支持 store-scope role，但 ServiceProvider 层未做"店长只能给本店员工分角色"的额外校验，留待业务出现再加
