<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 库存变化广播事件。Listener 可用于触发缓存失效、报表预聚合等。
 * 第一期不挂任何 Listener；事件存在仅为后续扩展。
 */
class StockChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $stockOwnerId,
        public readonly string $locationId,
        public readonly string $skuId,
        public readonly int $stockTxnId,
        public readonly string $bizType,
    ) {
    }
}
