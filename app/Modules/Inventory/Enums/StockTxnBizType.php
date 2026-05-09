<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

/**
 * 严格按 item_stock.md 行 266-277 的 12 个 biz_type。
 * 第一期实际写入仅前 4 项（ADJUSTMENT / STOCKTAKE_PROFIT / STOCKTAKE_LOSS / DAMAGE_OUT）。
 */
enum StockTxnBizType: string
{
    case PurchaseIn = 'PURCHASE_IN';
    case SaleOut = 'SALE_OUT';
    case ReturnIn = 'RETURN_IN';
    case ReturnOut = 'RETURN_OUT';
    case TransferOut = 'TRANSFER_OUT';
    case TransferIn = 'TRANSFER_IN';
    case StocktakeProfit = 'STOCKTAKE_PROFIT';
    case StocktakeLoss = 'STOCKTAKE_LOSS';
    case ProductionConsume = 'PRODUCTION_CONSUME';
    case ProductionOutput = 'PRODUCTION_OUTPUT';
    case Adjustment = 'ADJUSTMENT';
    case DamageOut = 'DAMAGE_OUT';
}
