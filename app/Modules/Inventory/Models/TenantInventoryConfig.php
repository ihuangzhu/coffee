<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Database\Factories\TenantInventoryConfigFactory;
use App\Modules\Inventory\Enums\InventoryCostMethod;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 租户级库存能力配置。每个 tenant 一行（通过 Observer 自动建立）。
 * 不挂 BelongsToTenant：本表的 tenant_id 即业务主键，不需要全局作用域过滤。
 */
class TenantInventoryConfig extends Model
{
    use HasFactory;
    use HasUlid;

    protected $table = 'tenant_inventory_configs';

    protected $guarded = [];

    protected $casts = [
        'inventory_enabled' => 'bool',
        'multi_location_enabled' => 'bool',
        'production_enabled' => 'bool',
        'purchase_enabled' => 'bool',
        'transfer_enabled' => 'bool',
        'stocktaking_enabled' => 'bool',
        'negative_stock_allowed' => 'bool',
        'inventory_cost_method' => InventoryCostMethod::class,
        'expiry_management_enabled' => 'bool',
        'batch_management_enabled' => 'bool',
        'auto_deduct_raw_material_enabled' => 'bool',
    ];

    protected static function newFactory(): TenantInventoryConfigFactory
    {
        return TenantInventoryConfigFactory::new();
    }
}
