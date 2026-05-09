<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

/** Item 所属层级：商户级（全租户共享）或门店私有。 */
enum OwnerType: string
{
    case Tenant = 'TENANT';
    case Store = 'STORE';
}
