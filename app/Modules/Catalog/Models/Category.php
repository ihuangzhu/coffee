<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\CategoryFactory;
use App\Modules\Catalog\Enums\CategoryItemTypeScope;
use App\Modules\Catalog\Enums\CategoryOwnerType;
use App\Modules\Catalog\Enums\CategoryStatus;
use App\Modules\Catalog\Enums\CategoryType;
use App\Support\Eloquent\BelongsToTenant;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUlid;
    use SoftDeletes;

    protected $table = 'categories';
    protected $guarded = [];
    protected $casts = [
        'owner_type' => CategoryOwnerType::class,
        'category_type' => CategoryType::class,
        'item_type_scope' => CategoryItemTypeScope::class,
        'status' => CategoryStatus::class,
        'level' => 'int',
        'sort_no' => 'int',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_no')->orderBy('name');
    }

    /**
     * 根分类 path='/'、level=1；子分类 path = parent.path + parent.id + '/'、level = parent.level + 1。
     */
    public function computePathAndLevel(?Category $parent): array
    {
        if ($parent === null) {
            return ['path' => '/', 'level' => 1];
        }
        return [
            'path' => $parent->path.$parent->id.'/',
            'level' => $parent->level + 1,
        ];
    }

    protected static function newFactory(): CategoryFactory
    {
        return CategoryFactory::new();
    }
}
