# 租户后台统一排版规范（Editorial Admin）

> 适用范围：租户后台所有业务页（`/tenant/**`），平台后台后续按同模式套用。
> 原型对象：用户管理列表 + 用户编辑（满意后推广至 Roles / Stores / Categories / Items / Stock / Profile / StoreUsers / Platform）。

## 设计方向

参照 Linear settings、Stripe dashboard、Notion admin。读者是店长/员工，需要密度而非装饰。

三条原则：

1. **Edge-to-edge canvas，内部节律严格**
   内容撑满视口（去除 `max-w-[1440px]` 上限），但所有间距走 `4 / 8 / 12 / 16 / 20 / 24` 一档，不允许 17px / 13px 这种自由值。
2. **层级靠字号 + 字重，不靠颜色**
   Indigo-600 仅留给 Primary CTA 和 active state；其余全部 slate 灰阶（900 / 600 / 400 三级）。
3. **常驻框架**
   PageHeader sticky 顶；表单页 ActionBar sticky 底；列表页表卡内的分页条 sticky 底；中间 body 滚动。

## 排版 token

### 垂直节律

| 元素 | 高度 / 间距 |
|---|---|
| TabBar | 36px |
| PageHeader（无 filter） | 56px |
| PageHeader（含 filter） | 96px |
| Header → Body | 16px |
| Card 内部 padding | 20px / 24px |
| Body → ActionBar | 24px |
| ActionBar | 52px (sticky bottom + border-top) |

### 水平

- 页面外层 `px-6` (24px)
- 不设 `max-width` 上限
- 长正文段落内置 `max-w-prose`
- 栅格：`grid-cols-12 gap-6`，断点 `lg` / `xl`

### 字体层级

| 角色 | 大小 / 字重 / 颜色 | 备注 |
|---|---|---|
| Page title | `20 / 600 / tracking-tight` slate-900 | PageHeader 标题 |
| Section title | `14 / 600` slate-900 | 卡内分节 |
| Body | `13.5 / 400` slate-700 | 正文 |
| Caption / 时间戳 | `12.5 / 400 / tabular-nums` slate-500 | 辅助 |
| 数字 | `tabular-nums` | 通用 |

### 颜色（沿用现有 token）

- Primary：`indigo-600`，仅 Primary CTA + active state
- Text：900 / 600 / 400 三级，禁用中间色
- Surface：white
- BG：`slate-50`
- Border：`slate-200` 硬线 / `slate-100` 软线（卡内分节用）

## 页面骨架

```
TenantLayout slot:
  flex-1 flex flex-col min-h-0 w-full px-6 pb-4

  ├── PageHeader (sticky top-0, -mx-6 px-6, edge-to-edge)
  │     ├── breadcrumb (可选)
  │     ├── title + actions
  │     └── #filter slot (可选，自带 mt-3)
  │
  ├── 16px gap (mt-4)
  │
  ├── Body (flex-1 min-h-0)
  │     列表 → DataTable，自身 flex column，分页条 sticky 卡底
  │     表单 → grid 12，左 7-8 表单卡 / 右 4-5 辅助
  │
  └── ActionBar (仅表单页, sticky bottom-0, -mx-6 px-6)
```

## 原型 A：用户管理列表

```
┌────────────────────────────────────────────────────────────────────────┐
│ [tabs]                                                                 │
├────────────────────────────────────────────────────────────────────────┤ sticky
│ 用户管理                                              [+ 新增用户]     │
│ [🔍 姓名或手机号_______] [筛选] 重置        16 条已筛选 · 共 84 条 →   │
├────────────────────────────────────────────────────────────────────────┤
│ ┌────────────────────────────────────────────────────────────────────┐ │
│ │ 姓名         手机号        租户级角色   创建时间          操作     │ │
│ │ ─────────────────────────────────────────────────────────────────  │ │
│ │ 张三         139…2018      商户管理员   05-09 14:23     编辑  ⋯    │ │
│ │ 李四         138…0099      —           04-21 09:11     编辑  ⋯    │ │
│ ├────────────────────────────────────────────────────────────────────┤ │
│ │ 共 84 条   每页 20 ▼     « ‹ 1 2 3 4 5 › »   跳至 [__]             │ │
│ └────────────────────────────────────────────────────────────────────┘ │ flex-1
└────────────────────────────────────────────────────────────────────────┘
```

**取舍**：
- 筛选条入 PageHeader `#filter` 插槽，去掉独立白卡片
- Filter 行右端追加 counter：`X 条已筛选 · 共 Y 条`，仅在 `q` 非空时渲染
- 操作列只保留 `编辑` + `⋯` 下拉（重置密码 / 删除入下拉）；详情页另有 Danger Zone，二者并存
- 表格区 `flex-1 min-h-0` 撑满剩余高度，DataTable 内分页条 sticky

## 原型 B：用户编辑

```
┌────────────────────────────────────────────────────────────────────────┐
│ [tabs]                                                                 │
├────────────────────────────────────────────────────────────────────────┤ sticky
│ 用户管理 / 张三                                                        │
│ 编辑用户                                                               │
├────────────────────────────────────────────────────────────────────────┤
│ ┌──────────────────────────────────┐ ┌────────────────────────────────┐│
│ │ 基本信息                          │ │ 张  张三                       ││
│ │ ─────────────────────────────────│ │     139****2018                ││
│ │ 姓名                              │ │ ─────────────                  ││
│ │ [_______________________]         │ │ 创建于    2025-12-04           ││
│ │                                   │ │ 上次登录  2026-05-08 16:42     ││
│ │ 手机号 (登录账号)                 │ │ 门店绑定  3 个门店             ││
│ │ [139****2018]   🔒                │ └────────────────────────────────┘│
│ │                                   │                                   │
│ │ 权限                              │ ┌────────────────────────────────┐│
│ │ ─────────────────────────────────│ │ 危险区                         ││
│ │ 租户级角色                        │ │ ─────────────                  ││
│ │ [商户管理员            ▼ ✕]       │ │ 重置密码         [操作]        ││
│ │ ↳ 清空后仅以门店级岗位身份工作    │ │ 删除用户         [操作]        ││
│ │                                   │ └────────────────────────────────┘│
│ └──────────────────────────────────┘                                    │
│         col-span 7 (xl) / 8 (lg)            col-span 5 / 4              │
├────────────────────────────────────────────────────────────────────────┤ sticky
│                                              [取消]  [保存修改]        │
└────────────────────────────────────────────────────────────────────────┘
```

**取舍**：
- 表单走「内部分节」（基本信息 / 权限）— 一张卡，靠 `border-t border-slate-100` 分段，**不**用多张卡片堆叠（堆叠让用户误以为是独立保存单元）
- 右侧 Identity Card：首字母 chip 替代头像（DB 无 `avatar_url`），元数据走「定义列表」节奏（label 左对齐 slate-500，value 右对齐 slate-900 mono）
- 右侧 Danger Zone：列表行内的「重置密码 / 删除」**同时迁入**详情页（按问答 #2，列表行也保留为冗余入口）
- ActionBar `保存修改` 替代泛化的 `保存`，动词具体化降低误操作

## 实现拆解

### 后端

`UserController::edit($id)` 增加 props：

```php
'user' => [
  'id' => ...,
  'name' => ...,
  'phone' => ...,
  'tenant_role_id' => ...,
  'created_at' => $u->created_at?->toDateTimeString(),
  'last_login_at' => $u->last_login_at?->toDateTimeString(),
  'store_binding_count' => $u->storeRoleBindings()
       ->where('tenant_id', $tenantId)->where('status', 'active')->count(),
],
```

如果 `users` 表无 `last_login_at`，本轮先输出 null 在前端走 dash；migration 单独排期。

### 前端组件

无新组件。改 3 个既有文件：

1. `layouts/TenantLayout.vue` — slot 容器去 max-w，加 `flex flex-col min-h-0`（已改）
2. `components/PageHeader.vue` — 紧凑化 padding 与字号（已改）
3. `components/DataTable.vue` — 改造为 flex column，`<el-table>` height auto / max-height auto，分页条 sticky 卡内底部

### 页面

- `pages/tenant/Users/Index.vue`：filter 入 PageHeader、counter、操作列收口为 `编辑 + ⋯ 下拉`
- `pages/tenant/Users/Edit.vue`：两栏栅格 + Identity / Danger 卡 + ActionBar

## 推广路径

原型确认后，按页面族复制规范：

1. **列表族**（Roles / Stores / Categories / Items / Stock / StoreUsers / Platform Tenants / Platform Stores）— 沿用 Users/Index 模式
2. **表单族**（Roles Edit / Roles Create / Users Create / StoreUsers Edit/Create / Items Edit/Create / Categories Edit/Create / Tenants Edit/Create / Stores Edit/Create）— 沿用 Users/Edit 模式
3. **窄表单族**（Stock Adjust / Damage / Stocktake）— 简化版：单列 max-w-2xl，不要右侧栏，ActionBar 沿用
4. **配置族**（Settings/InventoryConfig / Stores/InventoryConfig / Profile/Edit）— 表单族变体，可有多个 section

## 不在范围内

- 字体替换（B2B 中文 SaaS，沿用 system + Element Plus 默认即可）
- 新增 design token / Tailwind 配置
- 暗色主题
- DataTable 之外的新通用组件
