<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

/** 分类用途。BUSINESS=经营分类；INVENTORY=库存物料分类；BOTH=两者通用。 */
enum CategoryType: string
{
    case Business = 'BUSINESS';
    case Inventory = 'INVENTORY';
    case Both = 'BOTH';
}
