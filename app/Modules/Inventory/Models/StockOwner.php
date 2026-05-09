<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Database\Factories\StockOwnerFactory;
use App\Modules\Inventory\Enums\StockOwnerType;
use App\Support\Eloquent\BelongsToTenant;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockOwner extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUlid;

    protected $table = 'stock_owners';

    protected $guarded = [];

    protected $casts = [
        'owner_type' => StockOwnerType::class,
    ];

    protected static function newFactory(): StockOwnerFactory
    {
        return StockOwnerFactory::new();
    }
}
