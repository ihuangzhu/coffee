<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

enum StockOwnerType: string
{
    case Store = 'STORE';
    case Warehouse = 'WAREHOUSE';
    case ProductionArea = 'PRODUCTION_AREA';
}
