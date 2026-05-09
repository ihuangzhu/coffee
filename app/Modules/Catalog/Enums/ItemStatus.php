<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

enum ItemStatus: string
{
    case Active = 'active';
    case OffShelf = 'off_shelf';
}
