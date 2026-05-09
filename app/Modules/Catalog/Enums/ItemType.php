<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

/**
 * 物料类型。SALE_PRODUCT=可销售商品 / RAW_MATERIAL=原料 / SEMI_FINISHED=半成品
 * / FINISHED_GOOD=成品 / SERVICE=服务 / PACKAGE=包材。
 */
enum ItemType: string
{
    case SaleProduct = 'SALE_PRODUCT';
    case RawMaterial = 'RAW_MATERIAL';
    case SemiFinished = 'SEMI_FINISHED';
    case FinishedGood = 'FINISHED_GOOD';
    case Service = 'SERVICE';
    case Package = 'PACKAGE';
}
