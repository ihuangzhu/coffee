<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Exceptions;

use App\Support\Exceptions\BusinessException;

/**
 * 库存被某一层关闭（租户/门店/Item/SKU/policy.NONE）。
 * 携带 layer 属性指示哪一层关闭，便于调试与前端展示。
 */
class InventoryDisabledException extends BusinessException
{
    public function __construct(public readonly string $layer, public readonly string $resourceId)
    {
        parent::__construct(
            'INVENTORY_DISABLED',
            "库存能力在 [{$layer}] 层被关闭（resource={$resourceId}）",
            403,
        );
    }
}
