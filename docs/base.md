可以用一个清晰的“四层模型”来设计：**平台 → 商户 → 门店 → 用户/权限**。  
SaaS 里这类问题本质上是在解决 3 件事：

1. **数据隔离**
2. **组织层级归属**
3. **权限控制范围**

我按产品和技术结合的方式讲一个实用设计。

---

# 一、先定义核心对象关系

## 1. 平台（Platform）
SaaS 服务提供方，负责整个系统运营。

- 管理所有商户
- 配置套餐、功能开关、计费
- 不直接参与商户内部业务

---

## 2. 商户（Tenant / Merchant）
商户是 SaaS 中最核心的租户单位。

一个商户通常表示一家企业、品牌或客户组织。

### 商户下通常包含：
- 多个门店
- 多个员工/管理员
- 自己的订单、商品、会员、配置等业务数据

### 关键字段：
- tenant_id
- tenant_name
- status
- package_id
- created_at

### 设计原则：
**绝大多数业务表都要带 tenant_id**，用于租户级隔离。

---

## 3. 门店（Store / Shop / Branch）
门店是商户下的业务执行单元。

### 特点：
- 一个商户可以有多个门店
- 门店归属于某个商户
- 门店可能有自己的员工、库存、订单、营业配置

### 关键字段：
- store_id
- tenant_id
- store_name
- store_code
- status

### 关系：
- 商户 1:N 门店

---

## 4. 用户（User）
用户是登录主体，可以是：
- 平台管理员
- 商户管理员
- 门店员工

### 是否“一个人多个身份”？
建议支持。因为同一个手机号/账号可能：
- 在 A 商户是管理员
- 在 B 商户只是店员
- 在平台侧是运营人员（一般不建议与商户身份复用，但技术上可支持）

所以不要把用户直接等同于“某个商户员工”，而要拆成：

- **用户基础表 user**
- **用户与组织关系表 membership / employment**

---

# 二、推荐的模型：用户、组织、角色、权限分离

这是最稳妥的方式。

---

## 1. 用户基础表 `user`
存登录账号和基础身份信息。

### 示例字段：
- user_id
- name
- phone
- email
- password_hash
- status

这里只表示“这个人是谁”，**不直接定义他属于哪个商户、哪个门店、拥有什么权限**。

---

## 2. 用户归属关系表 `user_tenant_store_rel`
用于描述用户在某个商户/门店中的身份归属。

### 示例字段：
- id
- user_id
- tenant_id
- store_id（可为空）
- employee_no
- status

### 设计含义：
- `store_id = null`：表示用户属于商户级
- `store_id != null`：表示用户属于某个门店级

### 举例：
- 张三是商户总部管理员：`tenant_id=A, store_id=null`
- 李四是 A 商户上海门店店长：`tenant_id=A, store_id=SH001`

如果一个人管理多个门店，就多条关系记录。

---

# 三、管理员和权限怎么设计

核心建议：**不要把“管理员”做成固定字段，而是做成角色的一种。**

也就是说：

- 管理员不是一种用户类型
- 管理员是被授予了某些角色的用户

---

## 1. RBAC 模型最合适
推荐使用 **RBAC（Role-Based Access Control，基于角色的权限控制）**

基本结构：

- 用户 User
- 角色 Role
- 权限 Permission
- 用户角色关系 UserRole
- 角色权限关系 RolePermission

---

## 2. 权限粒度建议分三层

### A. 平台级权限
给 SaaS 平台内部人员使用。

例如：
- tenant:create
- tenant:disable
- package:update
- billing:view

### B. 商户级权限
给商户总部管理员使用。

例如：
- store:create
- staff:manage
- product:manage
- order:view_all_store
- report:view_tenant

### C. 门店级权限
给门店店长/店员使用。

例如：
- order:create
- order:view_self_store
- inventory:update_self_store
- member:view_self_store

---

## 3. 角色也要分作用域（Scope）
这是多商户多门店系统里最关键的一点。

如果只有“角色名”，不定义作用域，后期一定混乱。

### 角色表 `role`
字段示例：
- role_id
- role_name
- role_code
- scope_type：`platform / tenant / store`
- tenant_id（平台预置角色可为空；商户自定义角色可带 tenant_id）
- is_system

### 典型角色：
- 平台超级管理员（platform）
- 商户管理员（tenant）
- 商户运营（tenant）
- 门店店长（store）
- 门店店员（store）

---

## 4. 用户角色关系表 `user_role_binding`
这是权限设计里最重要的表之一。

### 示例字段：
- id
- user_id
- role_id
- tenant_id
- store_id（可为空）

### 含义：
角色绑定必须带上下文范围。

比如：

#### 场景1：商户管理员
- user_id=U1
- role_id=TenantAdmin
- tenant_id=T1
- store_id=null

表示：U1 是 T1 商户级管理员。

#### 场景2：门店店长
- user_id=U2
- role_id=StoreManager
- tenant_id=T1
- store_id=S1

表示：U2 是 T1 商户下 S1 门店的店长。

#### 场景3：跨门店管理
- U3 在 S1 是店长，在 S2 是普通店员
就绑定两条不同的角色关系。

---

# 四、管理员关系怎么理解最清晰

可以把“管理员”拆成 3 类：

## 1. 平台管理员
管理整个 SaaS 平台。
- 可管理所有商户
- 不一定能看到商户业务明细，视安全策略决定
- 一般仅限内部员工

## 2. 商户管理员
管理某个商户全局。
- 管门店
- 管员工
- 管商品、营销、配置
- 通常可看该商户下所有门店数据

## 3. 门店管理员
管理单个或多个门店。
- 管本门店员工排班、订单、库存等
- 只能访问授权门店数据

### 注意：
“管理员”不是一张单独表，而是**角色+作用域**的结果。

---

# 五、权限校验怎么做

权限校验不能只看“有没有这个权限”，还要看“权限作用范围”。

建议每次登录后生成一个**访问上下文 Session Context**：

### 上下文示例：
- user_id
- current_tenant_id
- current_store_id
- roles
- permissions
- data_scope

---

## 1. 权限判断 = 功能权限 + 数据权限

### 功能权限
能不能访问某个功能按钮/API  
例如：
- 是否允许创建门店
- 是否允许导出报表

### 数据权限
能看哪些数据  
例如：
- 只能看自己门店订单
- 能看商户全部门店订单
- 只能看自己创建的客户

---

## 2. 数据权限建议做成范围表达
例如角色上增加数据范围字段：

- ALL：商户全部数据
- STORE：指定门店数据
- SELF：本人数据
- CUSTOM：自定义门店集合/组织集合

### 例子：
商户运营经理：
- 权限：order:view
- 数据范围：ALL

门店店长：
- 权限：order:view
- 数据范围：STORE（S1）

店员：
- 权限：order:view
- 数据范围：SELF 或 STORE（受限字段）

---

# 六、数据库建模建议

下面给一个比较实用的表结构思路。

---

## 1. 基础组织表

### `tenant`
- tenant_id
- name
- status

### `store`
- store_id
- tenant_id
- name
- status

### `user`
- user_id
- name
- phone
- status

### `membership`
- id
- user_id
- tenant_id
- store_id nullable
- status

---

## 2. 权限模型表

### `permission`
- permission_id
- permission_code
- permission_name
- resource_type
- action

### `role`
- role_id
- role_name
- role_code
- scope_type (`platform/tenant/store`)
- tenant_id nullable
- is_system

### `role_permission_rel`
- id
- role_id
- permission_id

### `user_role_rel`
- id
- user_id
- role_id
- tenant_id
- store_id nullable

---

## 3. 业务表统一规范
例如订单表：

### `order`
- order_id
- tenant_id
- store_id
- created_by
- ...

商品表如果是商户统一商品：
- tenant_id
- store_id 可空

如果是门店独立商品或库存：
- tenant_id
- store_id 必填

### 原则：
**凡是业务数据，至少要有 tenant_id；如果与门店相关，再加 store_id。**

---

# 七、多商户隔离怎么选

技术上常见 3 种：

## 1. 共享数据库，共享表，tenant_id 隔离
最常见，适合大多数 SaaS 初期和成长期。

### 优点：
- 成本低
- 运维简单
- 扩展快

### 缺点：
- 隔离依赖程序控制
- 容易因 SQL 漏条件导致越权

### 适合：
- 中小型 SaaS
- 标准化程度高

---

## 2. 共享数据库，不同 Schema
每个商户独立 schema。

### 优点：
- 隔离更强
- 易做商户级备份恢复

### 缺点：
- 运维复杂
- 商户多时管理成本上升

---

## 3. 独立数据库
大客户专属库。

### 优点：
- 隔离最强
- 满足大客户合规要求

### 缺点：
- 成本高
- 研发和运维复杂

---

## 产品建议
大多数情况下采用：

- **默认：共享表 + tenant_id**
- **大客户：支持独立库升级**

这是最现实的 SaaS 演进路线。

---

# 八、产品层面的权限设计建议

---

## 1. 先预置标准角色
不要一开始就完全开放自定义权限，否则实施成本极高。

建议先有预置角色：
- 商户超级管理员
- 商户运营
- 财务
- 门店店长
- 门店店员

后续再支持商户自定义角色。

---

## 2. 页面选择“当前操作范围”
如果一个用户属于多个商户/多个门店，登录后要允许切换上下文：

- 当前商户
- 当前门店

这样前端展示和后端鉴权都更清晰。

---

## 3. 权限控制到 API，不只控制菜单
前端隐藏按钮不等于安全。  
必须后端接口按：

- user_id
- tenant_id
- store_id
- permission_code

做严谨校验。

---

## 4. 审计日志必须有
尤其是管理员操作：

- 谁在什么商户/门店上下文中
- 做了什么操作
- 修改前后内容
- 操作时间/IP/设备

这对排查权限问题非常重要。

---

# 九、一个最实用的关系总结

可以把关系简化成下面这句：

> **用户属于某个商户或门店；角色定义能做什么；角色绑定时定义在哪个商户/门店生效；业务数据通过 tenant_id/store_id 控制可见范围。**

---

# 十、推荐的最终方案

如果你要落地，我建议直接采用下面这个版本：

## 组织模型
- 平台
- 商户 tenant
- 门店 store

## 身份模型
- user：用户基础身份
- membership：用户与商户/门店关系

## 权限模型
- permission：权限点
- role：角色，带 scope_type
- role_permission_rel：角色权限
- user_role_rel：用户角色绑定，带 tenant_id/store_id

## 数据隔离模型
- 所有业务表必带 tenant_id
- 门店级业务表带 store_id
- 查询时统一按上下文注入过滤条件

## 管理员定义
- 平台管理员 = platform 作用域角色
- 商户管理员 = tenant 作用域角色
- 门店管理员 = store 作用域角色

---

# 十一、避坑提醒

最后给你几个常见坑：

## 1. 用户表里直接放 tenant_id
会导致一个用户无法跨商户、多身份复用。扩展性差。

## 2. 角色不带作用域
会出现“门店店长是否能看全部门店数据”这种混乱问题。

## 3. 只做菜单权限，不做数据权限
最后一定出现越权访问。

## 4. 业务表不带 tenant_id
后期几乎没法补，隔离和查询性能都会出问题。

## 5. 超级管理员直接绕过所有校验
短期方便，长期风险极高。平台管理员也应有明确边界和审计。

---

如果你愿意，我下一步可以继续给你输出两种更具体的内容之一：

1. **这套模型的 ER 图/表结构设计**
2. **Java/Spring Boot 或 Node.js 的权限校验落地方案**