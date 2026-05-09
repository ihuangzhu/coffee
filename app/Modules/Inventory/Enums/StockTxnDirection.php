<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

enum StockTxnDirection: string
{
    case In = 'IN';
    case Out = 'OUT';
    case Freeze = 'FREEZE';
    case Release = 'RELEASE';
}
