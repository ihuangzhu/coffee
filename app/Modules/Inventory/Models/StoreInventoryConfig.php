<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Database\Factories\StoreInventoryConfigFactory;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreInventoryConfig extends Model
{
    use HasFactory;
    use HasUlid;

    protected $table = 'store_inventory_configs';
    protected $guarded = [];

    protected $casts = [
        'inventory_enabled' => 'bool',
        'multi_location_enabled' => 'bool',
        'production_enabled' => 'bool',
        'allow_direct_stock_adjustment' => 'bool',
    ];

    protected static function newFactory(): StoreInventoryConfigFactory
    {
        return StoreInventoryConfigFactory::new();
    }
}
