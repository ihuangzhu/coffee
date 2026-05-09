<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Inventory\Database\Factories\BomFactory;
use App\Modules\Inventory\Enums\BomType;
use App\Support\Eloquent\BelongsToTenant;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bom extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUlid;
    use SoftDeletes;

    protected $table = 'boms';

    protected $guarded = [];

    protected $casts = [
        'bom_type' => BomType::class,
        'output_qty' => 'decimal:4',
    ];

    public function outputSku(): BelongsTo
    {
        return $this->belongsTo(ItemSku::class, 'output_sku_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(BomComponent::class);
    }

    protected static function newFactory(): BomFactory
    {
        return BomFactory::new();
    }
}
