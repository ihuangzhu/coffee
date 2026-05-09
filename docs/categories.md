分类必须单独设计，**不要把分类当成商品表上的一个普通字段草草带过**。  
因为在你这套系统里，分类不仅服务“展示”，还会影响：

- 商品/原料/半成品的管理入口
- 活动/优惠券适用范围
- 报表统计
- 门店可见范围
- 配方/BOM组织
- 采购与库存分析
- 租户公共分类和门店自定义分类

所以分类应该作为 **独立主数据体系** 插入到 `item + item_sku` 模型里。

---

# 一、先给结论：分类要分成两层看

我建议分类体系拆成：

## 1. 经营分类（Business Category）
给业务人员看的分类，用于：
- 商品管理
- 前台展示
- 营销活动
- 报表分析

## 2. 物料/库存分类（Inventory / Material Category）
给供应链和库存看的分类，用于：
- 原料管理
- 半成品管理
- 采购统计
- 库存分析
- 生产配方组织

---

## 为什么要拆？
因为现实里这两类分类经常不是一回事。

### 例子
一杯“珍珠奶茶”：
- 经营分类：饮品 > 奶茶
- 库存/物料分类：成品 > 现制饮品

一袋“珍珠原料”：
- 经营分类：前台根本不展示
- 库存/物料分类：原料 > 小料

所以如果你只做一个分类字段，后面会越来越别扭。

---

# 二、最简可落地方案：先做统一分类树，但支持分类类型

如果你当前不想一开始就做两套完全独立的分类体系，  
最好的折中做法是：

> **做一张统一分类表，但用 `category_type` 区分不同分类用途**

这样简单、可扩展。

---

# 三、推荐的分类模型

---

## 1. 分类主表 `category`
字段建议：

- category_id
- tenant_id
- owner_type：`TENANT / STORE`
- owner_store_id nullable
- category_type：
  - `BUSINESS` 经营分类
  - `INVENTORY` 库存/物料分类
  - `BOTH` 同时适用
- item_type_scope：
  - `SALE_PRODUCT`
  - `RAW_MATERIAL`
  - `SEMI_FINISHED`
  - `FINISHED_GOOD`
  - `SERVICE`
  - `ALL`
- parent_id
- category_name
- category_code
- level
- path
- sort_no
- status

---

## 字段解释

### `owner_type`
表示分类是谁维护的：
- `TENANT`：租户公共分类
- `STORE`：门店私有分类

这和商品归属逻辑一致。

---

### `category_type`
表示分类的用途：
- `BUSINESS`：前台商品分类、营销分类
- `INVENTORY`：库存/采购/原料分类
- `BOTH`：两边都能用

---

### `item_type_scope`
限制这个分类允许挂哪些 item 类型。  
例如：
- “奶茶”只允许挂 `SALE_PRODUCT / FINISHED_GOOD`
- “包材”只允许挂 `PACKAGE`
- “原料-乳制品”只允许挂 `RAW_MATERIAL`

这样能避免脏数据。

---

### `path`
树形结构路径，例如：
- `/1/12/38`

便于查整棵子树。

---

# 四、item 和 分类怎么关联

这里不要只在 `item` 上放一个 `category_id` 就结束。  
建议分两种阶段。

---

## 方案A：简单版
`item` 表直接有：

- business_category_id
- inventory_category_id

这个最实用。

---

### 优点
- 查询简单
- 90%场景够用
- 商品管理、库存管理、报表都能直接用

### 适合你当前阶段
如果你还在搭系统骨架，这个最推荐。

---

## 方案B：扩展版
单独做关联表：

### `item_category_rel`
- id
- tenant_id
- item_id
- category_id
- rel_type：`PRIMARY / SECONDARY / TAGGING`

### 适合场景
- 一个商品需要多分类
- 既挂主分类，又挂营销专题分类
- 做更复杂的搜索和运营

---

## 我的建议
### 当前先落地：
- `item.business_category_id`
- `item.inventory_category_id`

### 预留未来：
- 后续如果运营复杂，再扩 `item_category_rel`

不要一开始过度设计。

---

# 五、分类跟“租户公共商品、门店商品”怎么结合

你前面有：
- 租户公共商品
- 门店专属商品

分类也要匹配这个逻辑。

---

## 1. 租户公共分类
由总部维护，所有门店可用。

例子：
- 饮品
- 小吃
- 原料
- 包材
- 半成品

---

## 2. 门店私有分类
由门店自己维护，仅本店可见。

例子：
- 门店限定饮品
- 本店临时套餐
- 本店后厨自定义原料分组

---

## 3. 分类使用规则建议
### 规则1
租户商品可以挂：
- 租户公共分类
- 不建议挂其他门店私有分类

### 规则2
门店商品可以挂：
- 租户公共分类
- 自己门店私有分类

### 规则3
门店A商品不能挂门店B的私有分类

这个规则需要在服务层做校验。

---

# 六、分类与门店上架不是一回事

这点也要明确：

- **分类**：描述商品属于哪个业务分组
- **上架关系**：描述某商品在哪个门店售卖
- **库存**：描述某商品在某门店有多少库存

三者不要混。

---

# 七、分类如何支撑活动和优惠券

这部分非常重要。

因为很多营销场景不是指定单品，而是：
- 饮品 8 折
- 原料中的某些类目不参与
- 小吃满减
- 奶茶类第二杯半价

所以活动关联不能只支持商品，也要支持分类。

---

## 推荐活动范围模型
`promotion_target`
- target_type：
  - `ALL`
  - `CATEGORY`
  - `ITEM`
  - `SKU`
- target_id

当 `target_type = CATEGORY` 时，直接关联 `category_id`

---

## 判定逻辑
如果商品所属分类在活动分类树下，则命中活动。

例如活动指定：
- `饮品` 分类

则：
- 饮品下所有子类商品都参与

这就要求你的分类表支持树路径查询。

---

# 八、分类如何支撑库存与采购分析

如果你后面做进销存报表，分类很有价值。

---

## 1. 库存分析
按库存分类统计：
- 原料库存金额
- 包材库存金额
- 半成品库存金额

## 2. 采购分析
按分类统计：
- 本月乳制品采购额
- 小料采购损耗
- 包材使用成本

## 3. 销售分析
按经营分类统计：
- 饮品销售额
- 小吃销量
- 套餐转化率

所以经营分类和库存分类分开，报表会更清楚。

---

# 九、分类层级建议不要太深

实战里分类树不要无限深。

建议：

## 经营分类
2~3 级足够
例如：
- 饮品
  - 奶茶
  - 果茶
- 小吃
  - 炸物
  - 烘焙

## 库存/物料分类
2~4 级
例如：
- 原料
  - 乳制品
  - 茶底
  - 小料
- 包材
  - 杯
  - 吸管
  - 打包袋

层级过深会导致：
- 维护困难
- 活动配置复杂
- 报表口径混乱

---

# 十、分类是否要做编码体系

建议做。

### `category_code`
例如：
- B-DRINK
- B-DRINK-MILKTEA
- I-RAW-MILK
- I-PACK-CUP

好处：
- 便于对接外部系统
- 便于做报表口径
- 便于初始化模板

---

# 十一、分类是否支持排序、禁用、删除

建议支持。

---

## 必要字段
- sort_no
- status
- is_leaf（可选）
- deleted_flag

---

## 删除规则建议
如果分类下有：
- 子分类
- 已关联 item
- 已用于活动配置

则不允许物理删除，只能停用。

---

# 十二、分类插入到整个系统的位置

你问的是“分类应该如何插入”，我给你最直接的系统位置：

---

## 在主数据层插入
分类属于 **主数据体系**，和 item 并列，不属于库存层。

结构是：

```text
Tenant
 ├── Category
 ├── Item
 │    └── ItemSKU
 ├── StoreProductListing
 ├── StockBalance
 ├── BOM
 ├── Promotion
 └── Coupon
```

---

## item 挂分类
在 `item` 上挂：
- business_category_id
- inventory_category_id

---

## promotion / coupon 可挂分类
营销规则里支持：
- 指定经营分类
- 排除某分类

---

## 报表按分类聚合
销售、库存、采购、损耗都按分类维度统计。

---

# 十三、最推荐的当前落地方案

如果你要一个“够用且可扩展”的版本，我建议这样做：

---

## 1. 建一张统一分类表 `category`
支持：
- 租户公共分类 / 门店私有分类
- 经营分类 / 库存分类
- 树结构

---

## 2. 在 `item` 上加两个分类字段
- `business_category_id`
- `inventory_category_id`

---

## 3. 活动/优惠券支持按分类生效
营销目标支持：
- 全部商品
- 分类
- item
- sku

---

## 4. 报表按分类聚合
至少支持：
- 销售按经营分类
- 库存按库存分类
- 采购按库存分类

---

# 十四、一个更完整的字段示例

### `category`
```sql
category (
  category_id bigint pk,
  tenant_id bigint not null,
  owner_type varchar(20) not null,      -- TENANT / STORE
  owner_store_id bigint null,
  category_type varchar(20) not null,   -- BUSINESS / INVENTORY / BOTH
  item_type_scope varchar(50) not null, -- SALE_PRODUCT / RAW_MATERIAL / ALL ...
  parent_id bigint null,
  category_name varchar(100) not null,
  category_code varchar(64) null,
  level int not null default 1,
  path varchar(500) not null,
  sort_no int not null default 0,
  status varchar(20) not null,
  created_at datetime,
  updated_at datetime
)
```

### `item`
```sql
item (
  item_id bigint pk,
  tenant_id bigint not null,
  owner_type varchar(20) not null,
  owner_store_id bigint null,
  item_type varchar(30) not null,
  item_name varchar(100) not null,
  business_category_id bigint null,
  inventory_category_id bigint null,
  inventory_enabled tinyint not null default 1,
  sellable tinyint not null default 1,
  purchasable tinyint not null default 0,
  producible tinyint not null default 0,
  status varchar(20) not null
)
```

---

# 十五、最终建议一句话

**分类不要附属在商品上临时补，而要作为主数据体系独立建设；当前最优做法是“统一分类表 + 分类类型区分 + item 上双分类字段（经营分类/库存分类）”。**

---

如果你愿意，我下一步可以继续直接给你输出：

1. **完整主数据模型：item、category、sku 的 ER 结构**
2. **分类 + 活动 + 库存 + 配方联动的详细规则表**