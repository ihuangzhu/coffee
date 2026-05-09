/**
 * Permission enum 中文标签。
 * key 与 PHP App\Modules\Auth\Enums\Permission 的 value 严格一一对应；
 * 后端如新增 case 必须同步本文件，否则前端勾选矩阵显示原始 enum 字符串。
 *
 * 分组 key（domains）由后端 RolesController::groupedPermissions() 输出，前端无需重复定义。
 */
export default {
  domains: {
    credentials: '凭据管理',
    orders: '订单',
    users: '用户管理',
    roles: '角色管理',
    store: '门店管理',
    inventory: '库存',
    suppliers: '供货商',
    purchase_receipts: '采购入库',
    transfers: '调拨',
    stocktakes: '盘点',
    damages: '报损',
    purchase_returns: '供货商退货',
  },
  items: {
    'credentials.view': '查看凭据',
    'credentials.manage': '管理凭据',
    'orders.read': '查看订单',
    'orders.refund': '订单退款',
    'users.read': '查看用户',
    'users.invite': '邀请员工',
    'users.manage': '管理用户',
    'roles.manage': '管理角色',
    'store.users.manage': '管理门店人员',
    'inventory.read': '查看库存',
    'inventory.stocktake': '快速盘点修正',
    'suppliers.read': '查看供货商',
    'suppliers.manage': '管理供货商',
    'purchase_receipts.read': '查看采购入库单',
    'purchase_receipts.manage': '管理采购入库单',
    'transfers.read': '查看调拨单',
    'transfers.create': '创建调拨单',
    'transfers.receive': '调拨入库确认',
    'stocktakes.read': '查看盘点单',
    'stocktakes.create': '创建盘点单',
    'stocktakes.submit': '提交盘点单',
    'damages.read': '查看报损单',
    'damages.create': '创建报损单',
    'damages.cancel': '撤销报损单',
    'purchase_returns.read': '查看供货商退货单',
    'purchase_returns.create': '创建供货商退货单',
    'purchase_returns.submit': '提交供货商退货单',
  },
};
