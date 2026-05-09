# 租户后台顶部新增"商品"模块 设计文档

- 日期：2026-05-09
- 分支：feature/bom-produce
- 范围：租户后台一级导航重构

## 背景

当前租户后台 TopNav 有 3 个一级模块：仪表盘 / 库存 / 设置。"商品""分类"作为 sidebar 项藏在"库存"模块下。问题：

- 商品/分类是高频入口，路径太深（点"库存"再点"商品"才到 `/tenant/items`）。
- "库存"模块语义混杂：既包含商品主数据（商品、分类），又包含数量/流水（库存、流水、调整、盘点、报损），还有库存配置。
- 分支 `feature/bom-produce` 已实现 BOM CRUD（`TenantBomController` + `/tenant/boms` 页面），但 [resources/js/composables/useNavigation.ts](../../../resources/js/composables/useNavigation.ts) 里还没挂入口。这进一步印证了商品域会扩张。

## 目标

将商品域从库存模块剥离出来，在 TopNav 增加"商品"一级模块；同时把已存在但未挂载的 BOM 入口顺手接入。

## 非目标

- 不重命名后端路由或 Controller。
- 不改动任何权限码。
- 不影响总后台（platformModules）。
- 不修改 BOM 页面本身（已存在）。
- 不重命名既有 i18n key（`nav.inventory` 等沿用，语义自然收窄）。

## 设计

### 一级模块结构

租户后台 TopNav 模块从 3 个变 4 个，顺序固定：

```
仪表盘 · 商品 · 库存 · 设置
```

排序依据：

- 仪表盘是默认落地页，置于最左。
- 商品是主数据，库存是基于商品的运营数据 —— 先有定义、后有数量，所以"商品"在"库存"之前。
- 设置始终在最右。

### Sidebar 切分

**商品模块**（`key: 'items'`，`defaultUrl: /tenant/items`）

| key | icon | label (i18n) | url | permission |
| --- | --- | --- | --- | --- |
| categories | Folder | nav.categories | /tenant/categories | categories.read |
| items | Goods | nav.items | /tenant/items | items.read |
| boms | Connection | nav.bom | /tenant/boms | bom.read |

**库存模块**（`key: 'inventory'`，`defaultUrl: /tenant/stock`）

| key | icon | label (i18n) | url | permission |
| --- | --- | --- | --- | --- |
| stock | Box | nav.stock | /tenant/stock | inventory.read |
| stock-txns | List | nav.stock_txns | /tenant/stock/txns | stock_txn.read |
| stock-adjust | Tools | nav.stock_adjust | /tenant/stock/adjust | inventory.adjust |
| stock-stocktake | Histogram | nav.stocktake | /tenant/stock/stocktake | stocktake.write |
| stock-damage | Tickets | nav.damage | /tenant/stock/damage | damage.write |
| inventory-config | Setting | nav.inventory_config | /tenant/settings/inventory | inventory_config.read |

仪表盘、设置两个模块保持现状不变。

### 模块级权限

商品和库存两个模块的 `permission` 字段都留空（不在模块层做硬过滤）。可见性完全由 sidebar 项各自的 `permission` 决定：

- 只持有 `inventory.read` 的店员：TopNav 看到"库存"，看不到"商品"。
- 只持有 `items.read` 的运营：TopNav 看到"商品"，看不到"库存"。

注：`useMenu` 当前的 `visibleModules` 仅按 `module.permission` 过滤，无需修改即可工作 —— 因为只要 sidebar 至少一项可见，TopNav 模块本身就显示（`module.permission` 缺省即公开）。本设计不引入"模块下所有 sidebar 项均无权限时隐藏模块"的额外逻辑（YAGNI；当前用户角色规划下不会出现该场景）。

### `moduleByUrl` 路径归属

现有匹配逻辑（[useNavigation.ts:95-101](../../../resources/js/composables/useNavigation.ts#L95-L101)）：`pathname === defaultUrl || startsWith(defaultUrl) || 任一 sidebar.url 前缀命中`。

变更后归属：

- `/tenant/items`、`/tenant/items/*` → 商品模块（之前命中库存）
- `/tenant/categories`、`/tenant/categories/*` → 商品模块
- `/tenant/boms`、`/tenant/boms/*` → 商品模块（新）
- `/tenant/stock`、`/tenant/stock/*` → 库存模块
- `/tenant/settings/inventory` → 库存模块（被库存 sidebar 中 `inventory-config.url` 前缀命中；不会被"设置"模块抢匹配，因设置 sidebar 无任何 `/tenant/settings/inventory*` 前缀的 url）

### i18n 新增 key

- `nav.bom`：BOM/配方（中）/ BOM (英)

商品模块顶部 label 使用新 key（与 sidebar 项 `nav.items` 区分，避免顶部模块名和侧边栏菜单项重名带来的翻译歧义）：

- `nav.items_module`：商品 / Items

库存模块顶部 label 沿用 `nav.inventory`。中文文案"商品 / 库存"，英文"Items / Inventory"。

## 改动清单

### 必改

1. [resources/js/composables/useNavigation.ts](../../../resources/js/composables/useNavigation.ts)：重构 `tenantModules` 数组（拆 inventory → items + inventory，顺序 dashboard/items/inventory/settings，新增 boms 项）。
2. i18n 资源文件：新增 `nav.items_module`、`nav.bom`（中英两套）。

### 不改

- TopNav.vue / Sidebar.vue / useMenu.ts：完全数据驱动，零改动。
- 后端路由、Controller、权限码、BOM 页面：本次不动。
- platformModules：本次不动。

## 风险与回退

- **风险**：用户已建立"商品在库存里"的肌肉记忆，发布后短期内可能在库存模块下找不到商品。
  - **缓解**：新顺序中"商品"紧贴"库存"左侧，目视成本低；商品被提到顶部一级，平均路径反而更短。
- **回退**：完全在前端导航数据层，回滚只需还原 `useNavigation.ts` 一文件。无后端、无数据库、无权限改动。

## 验收

- TopNav 在租户后台显示 4 个 tab：仪表盘 / 商品 / 库存 / 设置。
- 进入 `/tenant/items` 时"商品"高亮；进入 `/tenant/stock` 时"库存"高亮。
- 商品模块 sidebar 显示分类 / 商品 / BOM；库存模块 sidebar 显示库存 / 流水 / 调整 / 盘点 / 报损 / 库存配置。
- 仅 `items.read` 用户登录后看不到库存模块；仅 `inventory.read` 用户看不到商品模块。
- `/tenant/settings/inventory` 仍归属库存模块（不被设置模块抢走）。
