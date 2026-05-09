可以，基于你上面的 **多商户 + 多门店** 体系，RBAC 设计重点不是“普通 RBAC”，而是 **带作用域的 RBAC**。  
否则角色一旦进入多租户、多门店场景，就会失控。

我建议直接设计成：

> **RBAC + Scope（作用域） + Data Permission（数据权限）**

也就是不仅要回答“他能做什么”，还要回答“他在哪儿能做”“能看哪些数据”。

---

# 一、先明确 RBAC 在这套系统里要解决什么问题

在你的场景里，权限控制至少要解决 3 个层面：

## 1. 功能权限
用户是否可以访问某个菜单、页面、按钮、API。

例如：
- 是否可以创建门店
- 是否可以编辑商品
- 是否可以导出订单

---

## 2. 组织范围权限
这个权限在哪个范围内生效。

例如：
- 在某个商户生效
- 在某个门店生效
- 在平台全局生效

---

## 3. 数据范围权限
即便有某个功能，也不代表能看全部数据。

例如：
- 可看当前门店订单
- 可看当前商户全部门店订单
- 只能看自己创建的客户

---

# 二、核心设计原则

先说结论，RBAC 在这个系统里要遵循 5 个原则：

## 原则1：角色不是全局角色，角色必须有作用域
角色必须区分：
- 平台级角色
- 商户级角色
- 门店级角色

否则“店长”和“商户管理员”的权限边界会混乱。

---

## 原则2：用户和角色的关系，必须带上下文
不能简单做 `user_id -> role_id`。

必须是：

- user_id
- role_id
- tenant_id
- store_id（可空）

因为同一个用户在不同商户、不同门店可以有不同角色。

---

## 原则3：权限点尽量标准化，角色只是权限集合
不要把“管理员”写死在代码里。  
应该是：

- 权限点定义能力
- 角色负责组合权限点
- 用户绑定角色获得权限

---

## 原则4：菜单权限、操作权限、数据权限要分开
很多系统失败在于只控制菜单，不控制 API 和数据。

建议拆成：
- 菜单权限：看不看得到页面
- 操作权限：能不能点按钮/调接口
- 数据权限：查出来的数据范围多大

---

## 原则5：后端鉴权为准，前端只做体验优化
前端隐藏按钮只是交互体验，真正安全控制一定在后端。

---

# 三、推荐的 RBAC 模型

推荐你用下面这套模型：

## 1. 权限 Permission
权限点是最小能力单元。

建议按“资源 + 动作”定义。

### 示例：
- `store:view`
- `store:create`
- `store:update`
- `store:delete`
- `staff:view`
- `staff:assign_role`
- `order:view`
- `order:refund`
- `report:export`

### 字段建议：
`permission`
- id
- code
- name
- resource_type
- action
- parent_id
- type（menu / action / api）
- status

---

## 2. 角色 Role
角色是权限点的组合，但角色必须带作用域类型。

### 字段建议：
`role`
- id
- role_name
- role_code
- scope_type：`platform / tenant / store`
- tenant_id（平台预置角色为空；商户自定义角色时有值）
- is_system
- status

### 示例角色：
平台级：
- PlatformSuperAdmin
- PlatformOps
- PlatformFinance

商户级：
- TenantAdmin
- TenantOps
- TenantFinance

门店级：
- StoreManager
- StoreCashier
- StoreClerk

---

## 3. 角色权限关系 RolePermission
定义一个角色拥有哪些权限点。

`role_permission_rel`
- id
- role_id
- permission_id

---

## 4. 用户角色绑定 UserRoleBinding
这是整个模型的核心。

### 字段建议：
`user_role_binding`
- id
- user_id
- role_id
- tenant_id
- store_id nullable
- effective_from
- effective_to
- status

### 为什么一定要 tenant_id/store_id？
因为同一个角色名，在不同上下文中是不同的授权结果。

例如：

#### 用户A
- 在 T1 商户下是 TenantAdmin
- 在 T2 商户下只是 StoreManager

#### 用户B
- 在 T1 的 S1 门店是店长
- 在 T1 的 S2 门店是普通店员

这都必须靠绑定表里的作用域来表达。

---

# 四、作用域怎么设计

这是多商户 RBAC 的关键。

---

## 1. 平台级作用域 `platform`
适用于 SaaS 平台内部人员。

### 权限范围：
- 管理商户开通、停用
- 管理套餐、计费、配置
- 查看平台运营数据

### 特征：
- 不依赖 tenant_id/store_id
- 全局有效

---

## 2. 商户级作用域 `tenant`
适用于某个商户总部人员。

### 权限范围：
- 管理本商户下所有门店
- 管理员工、角色、商品、订单规则
- 查看本商户全局报表

### 特征：
- 必须绑定 tenant_id
- store_id 通常为空

---

## 3. 门店级作用域 `store`
适用于门店店长、店员等。

### 权限范围：
- 管理或查看某个门店业务
- 仅对指定门店有效

### 特征：
- 必须绑定 tenant_id + store_id

---

# 五、数据权限怎么融合进 RBAC

标准 RBAC 只能回答“能不能做”，不擅长回答“能看哪些数据”。  
所以在 SaaS 场景里，一般要扩展成 **RBAC + Data Scope**。

---

## 1. 数据权限建议挂在“角色”上
因为同一类岗位通常数据范围一致。

可以在角色上增加：

- data_scope_type
- data_scope_rule

### 常见数据范围类型：
- `ALL`：当前商户全部数据
- `STORE`：当前绑定门店数据
- `SELF`：仅本人数据
- `CUSTOM_STORE`：自定义多个门店
- `NONE`

---

## 2. 示例
### 商户管理员 TenantAdmin
- scope_type = tenant
- data_scope = ALL

表示：
- 具备商户级权限
- 可看该商户下所有门店数据

### 门店店长 StoreManager
- scope_type = store
- data_scope = STORE

表示：
- 只看所绑定门店数据

### 销售员工 Sales
- scope_type = store
- data_scope = SELF

表示：
- 能看当前门店中自己负责的数据

---

## 3. 如果一个用户有多个角色怎么办
建议权限合并遵循：

- **功能权限取并集**
- **数据范围取并集，但不能越出租户边界**

例如用户在 T1 商户下：
- 角色A：看 S1 门店订单
- 角色B：看 S2 门店订单

最终结果：
- 可看 S1 + S2

但绝不能因为角色合并跨到 T2 商户。

---

# 六、权限点怎么分层设计

建议把权限点分 4 层，这样后期最好维护。

---

## 1. 菜单权限
控制左侧菜单、页面入口是否可见。

例如：
- `menu:store`
- `menu:order`
- `menu:report`

---

## 2. 页面操作权限
控制页面内按钮、操作行为。

例如：
- `store:create`
- `store:update`
- `order:refund`
- `report:export`

---

## 3. API 权限
后端接口权限，和操作权限对应。

例如：
- `api:/stores [GET]`
- `api:/stores [POST]`
- `api:/orders/{id}/refund [POST]`

一般不建议前端直接用 API 权限码展示，而是后端内部映射即可。

---

## 4. 数据权限
控制 SQL/查询结果范围。

例如：
- 订单仅限当前 tenant_id
- 或仅限当前 store_id
- 或仅限 created_by = current_user

---

# 七、推荐的表结构设计

---

## 1. 权限表
```sql
permission (
  id bigint pk,
  code varchar,
  name varchar,
  type varchar,         -- menu/action/api
  resource_type varchar,
  action varchar,
  parent_id bigint,
  path varchar,
  method varchar,
  status tinyint
)
```

---

## 2. 角色表
```sql
role (
  id bigint pk,
  role_name varchar,
  role_code varchar,
  scope_type varchar,   -- platform/tenant/store
  tenant_id bigint null,
  data_scope_type varchar,   -- ALL/STORE/SELF/CUSTOM_STORE/NONE
  is_system tinyint,
  status tinyint
)
```

---

## 3. 角色权限关系表
```sql
role_permission_rel (
  id bigint pk,
  role_id bigint,
  permission_id bigint
)
```

---

## 4. 用户角色绑定表
```sql
user_role_binding (
  id bigint pk,
  user_id bigint,
  role_id bigint,
  tenant_id bigint null,
  store_id bigint null,
  status tinyint,
  effective_from datetime,
  effective_to datetime
)
```

---

## 5. 自定义数据范围表（可选）
如果支持“自定义多个门店可见”，建议单独建表。

```sql
role_data_scope_store_rel (
  id bigint pk,
  role_id bigint,
  store_id bigint
)
```

或做成用户级授权：

```sql
user_role_store_scope_rel (
  id bigint pk,
  user_role_binding_id bigint,
  store_id bigint
)
```

我更推荐第二种，因为它更贴近“某个用户在某次角色绑定下可访问哪些门店”。

---

# 八、登录和鉴权流程怎么设计

---

## 1. 登录后获取用户全部授权信息
系统查询：
- 用户属于哪些商户/门店
- 在每个范围下有哪些角色
- 每个角色包含哪些权限
- 对应哪些数据范围

---

## 2. 生成授权上下文
例如：

```json
{
  "user_id": 1001,
  "current_tenant_id": 2001,
  "current_store_id": 3001,
  "roles": ["StoreManager"],
  "permissions": ["order:view", "order:refund", "inventory:update"],
  "data_scope": {
    "type": "STORE",
    "store_ids": [3001]
  }
}
```

---

## 3. 每次请求做两层判断

### 第一层：功能权限校验
检查当前用户是否具备接口或操作所需权限码。

例如调用“退款”接口：
- 必须具备 `order:refund`

---

### 第二层：数据范围校验
检查这笔订单是否属于：
- 当前 tenant_id
- 当前授权 store_id 范围

也就是说：
> 有退款权限，不等于能退所有门店订单。

---

# 九、后端落地建议

---

## 1. 统一权限注解
例如在 Java/Spring Boot 中：

```java
@RequiresPermission("order:refund")
```

或：

```java
@PreAuthorize("hasPermission('order:refund')")
```

---

## 2. 统一数据权限拦截器
在查询层统一注入：
- tenant_id = currentTenantId
- store_id in authorizedStoreIds
- created_by = currentUserId（如果是 SELF）

不要让业务开发每次手写，容易漏。

---

## 3. 超级管理员也要有边界
平台超级管理员不要默认绕过所有租户隔离。  
建议分两种：

- 平台运营权限：可管理租户配置
- 商户数据访问权限：需要显式授权或临时授权审计

否则会有严重合规风险。

---

# 十、推荐的角色设计方式

不要一开始开放无限自定义，建议分两层：

## 第一层：系统预置角色
例如：

### 平台侧
- 平台超级管理员
- 平台运营
- 平台财务

### 商户侧
- 商户管理员
- 商户运营
- 商户财务

### 门店侧
- 门店店长
- 收银员
- 店员

---

## 第二层：商户自定义角色
允许商户自己基于权限点组合角色，比如：
- 区域经理
- 仓库主管
- 售后专员

但限制：
- 只能在自己 tenant 内创建
- 不能越权分配自己没有的权限

---

# 十一、关键规则：谁能给谁授权

这点经常被忽略，但非常重要。

建议规则如下：

## 1. 平台管理员
可给平台用户分配平台角色  
可给商户初始化默认管理员  
一般不直接参与商户内部日常授权

---

## 2. 商户管理员
可给本商户用户分配：
- 商户级角色
- 门店级角色

但只能分配：
- 自己拥有授权能力范围内的角色
- 且仅限本 tenant

---

## 3. 门店店长
如果业务需要，可允许给本门店员工分配有限角色  
例如只可分配“店员”“收银员”，不可分配“商户管理员”

---

## 一个常用控制方法：
给角色增加“可授权角色集”。

例如：
- TenantAdmin 可授予 StoreManager / StoreClerk
- StoreManager 可授予 StoreClerk
- StoreClerk 不可授予任何角色

---

# 十二、典型场景举例

---

## 场景1：商户管理员
角色：
- TenantAdmin

绑定：
- user_id=U1
- tenant_id=T1
- store_id=null

权限：
- 门店管理
- 员工管理
- 商品管理
- 报表查看

数据范围：
- T1 下全部门店

---

## 场景2：一个用户管理多个门店
角色：
- StoreManager

绑定：
- U2 - T1 - S1
- U2 - T1 - S2

数据范围：
- S1 + S2

---

## 场景3：跨商户兼职
绑定：
- U3 - T1 - StoreClerk - S1
- U3 - T2 - TenantOps - null

登录后必须选择“当前商户上下文”，否则权限会混乱。

---

# 十三、容易踩的坑

---

## 1. 角色不带作用域
这是最大坑。  
不带作用域就无法准确表达“这个角色是在哪个商户/门店下生效”。

---

## 2. 只做功能权限，不做数据权限
结果是用户能通过接口查到不该看的数据。

---

## 3. 用户只绑定一个角色
实际场景里，一个人常常多个角色并存，必须支持多角色。

---

## 4. 数据权限写死在业务代码里
后期维护会非常痛苦。  
应抽象成统一规则引擎或统一拦截层。

---

## 5. 角色可随意授权
如果不限制“谁可以授予什么角色”，很容易出现权限扩散。

---

# 十四、最推荐的最终权限模型

我建议你最终采用这一版：

## 模型
- User
- Role
- Permission
- RolePermissionRel
- UserRoleBinding（带 tenant/store 作用域）
- DataScope（挂角色或挂绑定关系）

## 权限判断
**用户是否可操作 = 角色权限校验 + 当前上下文匹配 + 数据范围校验**

即：

```text
Allow = HasPermission
        AND MatchScope
        AND MatchDataScope
```

---

# 十五、一句话总结

在多商户多门店 SaaS 中，RBAC 不能只设计成“用户-角色-权限”三张表，  
而应该设计成：

> **用户 + 角色 + 权限 + 角色作用域 + 数据范围**

否则只能解决“能不能点按钮”，解决不了“在哪个商户/门店能点、能看到哪些数据”。

---

如果你愿意，我下一步可以继续直接给你两种更落地的内容：

1. **完整 RBAC 数据库表结构 SQL**
2. **权限校验流程图 + 接口鉴权时序图**