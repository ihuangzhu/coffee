<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

enum CategoryStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}
