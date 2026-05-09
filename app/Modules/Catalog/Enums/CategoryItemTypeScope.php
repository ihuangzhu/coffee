<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

/** 分类挂载范围限制：限定本分类只能挂哪些 item.item_type。'ALL' 表示不限。 */
enum CategoryItemTypeScope: string
{
    case SaleProduct = 'SALE_PRODUCT';
    case RawMaterial = 'RAW_MATERIAL';
    case SemiFinished = 'SEMI_FINISHED';
    case FinishedGood = 'FINISHED_GOOD';
    case Service = 'SERVICE';
    case Package = 'PACKAGE';
    case All = 'ALL';
}
