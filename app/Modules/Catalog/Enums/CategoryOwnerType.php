<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

/** 分类的所有者层级。TENANT=租户公共分类（所有门店可见）；STORE=门店私有分类。 */
enum CategoryOwnerType: string
{
    case Tenant = 'TENANT';
    case Store = 'STORE';
}
