/**
 * 后端枚举常量 → 中文标签映射。
 * 后端权威定义见 app/Modules/{Catalog,Inventory}/Enums/*；前端只关心展示。
 *
 * 命名空间：`enums.<domain>.<CODE>`
 * 在模板可直接 $t('enums.item_type.SALE_PRODUCT')；
 * 也可走 lib/itemTypes.ts 的 helper（itemTypeLabel 等），二者等价。
 */
export default {
  /** 商品 / 物料类型 */
  item_type: {
    SALE_PRODUCT: '可销售商品',
    RAW_MATERIAL: '原料',
    SEMI_FINISHED: '半成品',
    FINISHED_GOOD: '成品',
    SERVICE: '服务',
    PACKAGE: '包材',
  },
  /** 分类挂载范围 = item_type ∪ {ALL} */
  item_type_scope: {
    ALL: '全部',
    SALE_PRODUCT: '可销售商品',
    RAW_MATERIAL: '原料',
    SEMI_FINISHED: '半成品',
    FINISHED_GOOD: '成品',
    SERVICE: '服务',
    PACKAGE: '包材',
  },
  /** 分类业务类型 */
  category_type: {
    BUSINESS: '经营',
    INVENTORY: '库存物料',
    BOTH: '通用',
  },
  /** 分类 / 商品所有者类型 */
  owner_type: {
    TENANT: '租户公共',
    STORE: '门店私有',
  },
  /** 库存流水业务类型 */
  stock_txn_biz_type: {
    PURCHASE_IN: '采购入库',
    SALE_OUT: '销售出库',
    RETURN_IN: '退货入库',
    RETURN_OUT: '退货出库',
    TRANSFER_IN: '调拨入库',
    TRANSFER_OUT: '调拨出库',
    STOCKTAKE_PROFIT: '盘盈',
    STOCKTAKE_LOSS: '盘亏',
    PRODUCTION_CONSUME: '生产消耗',
    PRODUCTION_OUTPUT: '生产入库',
    ADJUSTMENT: '调整',
    DAMAGE_OUT: '报损',
  },
  /** 库存流水方向 */
  stock_txn_direction: {
    IN: '入',
    OUT: '出',
    FREEZE: '冻结',
    RELEASE: '解冻',
  },
};
