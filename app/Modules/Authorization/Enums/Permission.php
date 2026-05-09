<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Enums;

/**
 * 商户域权限（INV-A：所有 case 不可有 platform. 前缀）。
 *
 * 单一来源：写入 roles.permissions 时仅接受这里枚举值的 string。
 * 后续 Goods / Order / Inventory 等模块各自 PR 在此追加 case。
 */
enum Permission: string
{
    case RolesRead = 'roles.read';
    case RolesManage = 'roles.manage';
    case UsersRead = 'users.read';
    case UsersAssignRole = 'users.assign-role';
    case TenantRead = 'tenant.read';
    case StoresRead = 'stores.read';

    // ── Inventory 模块（Task 20）
    case ItemsRead = 'items.read';
    case ItemsWrite = 'items.write';
    case ItemSkusRead = 'item_skus.read';
    case ItemSkusWrite = 'item_skus.write';
    case CategoriesRead = 'categories.read';
    case CategoriesWrite = 'categories.write';
    case InventoryRead = 'inventory.read';
    case InventoryAdjust = 'inventory.adjust';
    case StocktakeWrite = 'stocktake.write';
    case DamageWrite = 'damage.write';
    case StockTxnRead = 'stock_txn.read';
    case StockTxnReverse = 'stock_txn.reverse';
    case InventoryConfigRead = 'inventory_config.read';
    case InventoryConfigUpdate = 'inventory_config.update';
    case InventoryPolicyRead = 'inventory_policy.read';
    case InventoryPolicyUpdate = 'inventory_policy.update';

    // ── BOM / 生产入库（2026-05-09 BOM Action 阶段）
    case BomsRead = 'boms.read';
    case BomsCreate = 'boms.create';
    case BomsUpdate = 'boms.update';
    case BomsDelete = 'boms.delete';
    case ProductionExecute = 'production.execute';
    case ProductionRead = 'production.read';
}
