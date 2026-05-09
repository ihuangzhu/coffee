要把这套系统做成“能从轻到重逐步启用”的进销存平台，核心不是先把表堆全，而是先做一套 **分层能力模型**：

> **租户能力开关 + 商品库存策略 + 门店库存组织方式 + 库存交易流水 + 生产/配方关系 + 采购销售闭环**

这样才能同时支持：
- 有些租户根本不用库存
- 有些只做简单库存
- 有些要货架位
- 有些商品不记库存
- 有些商品是自制品，需要扣减原料
- 有些还要完整采购、调拨、盘点、生产、报损

我从“整体架构”角度给你一套可落地方案。

---

# 一、先定总原则：这不是一个“单模块”，而是一个能力体系

建议整体拆成 6 层：

1. **租户能力配置层**
2. **商品与物料主数据层**
3. **库存组织层**
4. **库存交易层**
5. **业务单据层**
6. **生产/配方/BOM层**

---

# 二、先解决配置问题：租户是否启用哪些能力

这是第一层，必须前置。

因为不是每个租户都需要复杂进销存。  
所以要做 **Feature Toggle + Business Policy** 两级配置。

---

## 1. 租户级能力开关
建议建立一套租户配置表：

### `tenant_inventory_config`
字段建议：
- tenant_id
- inventory_enabled                -- 是否启用库存模块
- multi_location_enabled           -- 是否启用多货架/库位
- production_enabled               -- 是否启用自制商品/配方
- purchase_enabled                 -- 是否启用采购
- transfer_enabled                 -- 是否启用调拨
- stocktaking_enabled              -- 是否启用盘点
- negative_stock_allowed           -- 是否允许负库存
- inventory_cost_method            -- FIFO / MOVING_AVG / STANDARD
- expiry_management_enabled        -- 是否启用保质期/批次
- batch_management_enabled         -- 是否启用批次
- auto_deduct_raw_material_enabled -- 销售自制商品时是否自动扣减原料

---

## 2. 门店级配置
有些能力可能租户开了，但并不是所有门店都用。

### `store_inventory_config`
字段建议：
- store_id
- inventory_enabled
- multi_location_enabled
- default_stock_mode
- production_enabled
- allow_direct_stock_adjustment

### 适用场景：
- 总部开了库存模块
- A门店精细化管理库存
- B门店只做销售不管库存

---

## 3. 商品级库存策略
不是所有商品都需要记库存。

### `product_inventory_policy`
字段建议：
- sku_id
- tenant_id
- inventory_track_type：`NONE / FINISHED_GOOD / RAW_MATERIAL / BOTH`
- stock_deduct_mode：`SALE_DEDUCT / MANUAL_DEDUCT / PRODUCTION_DEDUCT`
- allow_negative_stock
- batch_required
- expiry_required

### 含义：
- `NONE`：不记库存，例如服务类商品、次卡、虚拟券
- `FINISHED_GOOD`：成品库存
- `RAW_MATERIAL`：原料库存
- `BOTH`：既作为成品卖，也可作为原料使用

这个设计很关键，因为很多商品有“双重身份”。

---

# 三、商品体系要升级为“商品 + 物料 + 配方”

你现在不能只把所有东西都叫“商品”，否则后面原料、自制品、组合品会乱。

建议统一抽象成 **Item（物料主数据）**，然后通过类型区分。

---

## 1. 统一物料主数据 `item`
字段建议：
- item_id
- tenant_id
- item_type：
  - `SALE_PRODUCT` 销售商品
  - `RAW_MATERIAL` 原料
  - `SEMI_FINISHED` 半成品
  - `FINISHED_GOOD` 成品
  - `SERVICE` 服务
  - `PACKAGE` 包材
- owner_type：TENANT / STORE
- owner_store_id
- item_name
- category_id
- unit_id
- sku_enabled
- inventory_enabled
- status

---

## 2. 为什么建议统一成 item，而不是商品表/原料表分开？
因为现实中经常出现：
- 一瓶牛奶既可直接销售，也可作为原料
- 一个蛋糕胚既是半成品，也能单卖
- 一个套餐是销售商品，但由多个原料组成

统一主数据后，用类型和策略区分，扩展性最好。

---

## 3. SKU 层继续保留
`item_sku`
- sku_id
- item_id
- spec_json
- barcode
- sale_price
- cost_price
- inventory_enabled

---

# 四、库存组织层怎么设计

库存不是只有“门店一个数字”，而是有层级的。

建议设计成：

> **租户 → 门店 → 库存主体 → 库位/货架**

---

## 1. 库存主体（Stock Owner）
先区分库存属于谁。

### 常见库存主体：
- 门店前台库存
- 门店后仓库存
- 中央仓（如果未来有）
- 生产区库存

建议有统一表：

### `stock_owner`
- stock_owner_id
- tenant_id
- owner_type：`STORE / WAREHOUSE / PRODUCTION_AREA`
- owner_ref_id
- name
- status

如果当前只做门店库存，也可以先简化为 store 直接作为 stock owner。

---

## 2. 多货架/多库位
如果租户启用多货架，就在库存主体下再细分库位。

### `stock_location`
- location_id
- tenant_id
- stock_owner_id
- location_code
- location_name
- location_type：`SHELF / FREEZER / DISPLAY / BACKROOM`
- status

### 场景：
- 门店A有前台陈列架、冷藏柜、后仓
- 没开多货架的租户，就默认一个“默认库位”

---

## 3. 库存表分两层
建议做：
- **汇总库存**
- **明细库存**

---

### A. 汇总库存 `stock_balance`
按最常用查询维度汇总，便于业务查询。

字段建议：
- tenant_id
- stock_owner_id
- location_id（可空）
- sku_id
- available_qty
- reserved_qty
- in_transit_qty
- damaged_qty
- version

如果没启用多货架，`location_id` 可为空或指向默认库位。

---

### B. 库存批次明细 `stock_quant`
如果启用批次/保质期/成本分层，建议做明细层。

字段建议：
- quant_id
- tenant_id
- stock_owner_id
- location_id
- sku_id
- batch_no
- expiry_date
- unit_cost
- qty

这层用于：
- FIFO
- 批次追踪
- 保质期管理

如果租户没启用批次，可以不使用或只用默认一条 quant。

---

# 五、库存系统核心不是“库存表”，而是“库存流水”

这是进销存最重要的一点。

不要让采购、销售、盘点直接改库存数字。  
必须设计统一的 **库存交易流水**。

---

## 1. 库存流水表 `stock_txn`
字段建议：
- txn_id
- tenant_id
- biz_type：
  - PURCHASE_IN
  - SALE_OUT
  - RETURN_IN
  - RETURN_OUT
  - TRANSFER_OUT
  - TRANSFER_IN
  - STOCKTAKE_PROFIT
  - STOCKTAKE_LOSS
  - PRODUCTION_CONSUME
  - PRODUCTION_OUTPUT
  - ADJUSTMENT
  - DAMAGE_OUT
- biz_order_type
- biz_order_id
- stock_owner_id
- location_id
- sku_id
- qty_change
- unit_cost
- amount
- direction：IN / OUT / FREEZE / RELEASE
- occurred_at
- operator_id

---

## 2. 好处
- 所有库存变化可追溯
- 可做对账
- 可重建库存
- 支持审计
- 支持复杂业务扩展

---

## 3. 汇总库存怎么来？
每次库存流水落地后，同步更新 `stock_balance`。  
也就是：

- `stock_txn` 是事实账
- `stock_balance` 是结果账

---

# 六、“商品是否记库存”怎么做

这个需求一定要放到 **SKU/物料级策略**，不要只在租户级判断。

---

## 1. 三层控制逻辑
### 第一层：租户是否启用库存
如果租户未启用：
- 不做库存扣减
- 不展示库存功能
- 相关单据可禁用

### 第二层：门店是否启用库存
如果门店未启用：
- 该门店销售不校验库存
- 不生成门店库存流水

### 第三层：商品/SKU是否记库存
如果 SKU 配置 `inventory_enabled = false`
- 销售时不扣库存
- 不参与盘点、调拨、采购入库

---

## 2. 典型场景
### 不记库存
- 服务项目
- 卡券
- 定制项
- 虚拟套餐

### 记库存
- 零售商品
- 原料
- 包材
- 成品

---

# 七、自制商品与原料库存怎么设计

这是你这套系统从“零售库存”走向“轻生产/餐饮配方”的关键。

核心设计：

> **自制商品 = 成品 SKU**
> **原料 = 物料 SKU**
> **两者通过 BOM / Recipe 关联**
> **发生生产或销售时扣减原料、增加/扣减成品**

---

## 1. 配方/BOM 表
### `bom`
- bom_id
- tenant_id
- output_sku_id      -- 产出成品
- output_qty
- bom_type：STANDARD / STORE_CUSTOM
- store_id nullable
- status

### `bom_component`
- id
- bom_id
- component_sku_id   -- 原料SKU
- consume_qty
- loss_rate
- sequence_no

---

## 2. 两种扣减模式要区分
现实中自制商品有两种模式：

### 模式A：先生产后销售
例子：
- 早上做 20 个面包入库
- 白天卖面包时扣成品库存

流程：
1. 生产领料：原料出库
2. 成品入库：面包入库
3. 销售时：面包出库

适合：
- 烘焙
- 中央厨房
- 半成品管理

---

### 模式B：销售时即时扣原料
例子：
- 奶茶下单后现做
- 不单独管成品库存，只扣珍珠、奶、茶叶

流程：
1. 销售订单完成
2. 系统根据 BOM 自动扣原料
3. 不记录成品库存，或成品库存不重要

适合：
- 餐饮、现制饮品

---

## 3. 所以自制商品需要一个生产策略字段
在商品或 BOM 上加：

- production_mode：
  - `MAKE_TO_STOCK` 先生产入库
  - `MAKE_TO_ORDER` 按单扣原料

---

# 八、整体业务单据体系怎么搭

进销存系统大，关键不是表多，而是单据清晰。

建议业务单据分成这些模块：

---

## 1. 采购模块
- 采购订单
- 采购收货单
- 采购退货单
- 供应商

库存影响：
- 收货入库
- 退货出库

---

## 2. 销售模块
- 销售订单
- 销售出库 / 零售扣减
- 销售退货

库存影响：
- 销售出库
- 退货入库

---

## 3. 调拨模块
- 调拨申请单
- 调拨出库单
- 调拨入库单

库存影响：
- 转出门店减
- 转入门店增
- 中间可有在途库存

---

## 4. 盘点模块
- 盘点任务
- 盘点明细
- 盘盈盘亏单

库存影响：
- 调整差异

---

## 5. 报损报废模块
- 报损单
- 报废单

库存影响：
- 非销售出库

---

## 6. 生产模块
- 生产工单
- 领料单
- 产出入库单

库存影响：
- 原料出库
- 成品入库

---

# 九、从产品规划角度，千万不要一次做满

这套系统很大，建议按阶段建设，否则项目会失控。

---

## 阶段1：轻库存版
适合大多数 SaaS 初始租户

能力：
- 租户可选是否启用库存
- 商品可配置是否记库存
- 门店级库存
- 销售扣库存
- 手工入库/出库/调整
- 简单盘点
- 公共商品/门店商品

不做：
- 多货