<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Enums;

enum StoreStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}
