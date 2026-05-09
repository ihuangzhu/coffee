<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

enum BomType: string
{
    case Standard = 'STANDARD';
    case StoreCustom = 'STORE_CUSTOM';
}
