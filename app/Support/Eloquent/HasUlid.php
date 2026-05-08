<?php

declare(strict_types=1);

namespace App\Support\Eloquent;

use Illuminate\Support\Str;

/**
 * 主键使用 ULID（26 字符）。
 *
 * 应用层生成，绝不依赖数据库自增——为未来分库分表做准备。
 * ULID 时间有序，B-Tree 索引友好，且不暴露增长速率。
 */
trait HasUlid
{
    /**
     * 模型 boot 钩子：监听 creating 事件，主键为空时自动填充 ULID。
     */
    public static function bootHasUlid(): void
    {
        static::creating(function ($model) {
            $key = $model->getKeyName();
            if (empty($model->{$key})) {
                $model->{$key} = (string) Str::ulid();
            }
        });
    }

    /**
     * 禁用自增主键——主键由应用层 ULID 生成。
     */
    public function getIncrementing(): bool
    {
        return false;
    }

    /**
     * 主键类型为字符串（ULID 26 位字符）。
     */
    public function getKeyType(): string
    {
        return 'string';
    }
}
