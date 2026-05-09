<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Database\Factories\StockLocationFactory;
use App\Modules\Inventory\Enums\StockLocationType;
use App\Support\Eloquent\BelongsToTenant;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLocation extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUlid;

    protected $table = 'stock_locations';

    protected $guarded = [];

    protected $casts = [
        'location_type' => StockLocationType::class,
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(StockOwner::class, 'stock_owner_id');
    }

    protected static function newFactory(): StockLocationFactory
    {
        return StockLocationFactory::new();
    }
}
