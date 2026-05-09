<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

enum StockLocationType: string
{
    case Shelf = 'SHELF';
    case Freezer = 'FREEZER';
    case Display = 'DISPLAY';
    case Backroom = 'BACKROOM';
}
