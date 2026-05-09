<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

enum InventoryCostMethod: string
{
    case Fifo = 'FIFO';
    case MovingAverage = 'MOVING_AVG';
    case Standard = 'STANDARD';
}
