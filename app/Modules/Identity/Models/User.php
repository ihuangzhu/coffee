<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Database\Factories\UserFactory;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * 全局身份模型（不挂 BelongsToTenant，跨租户单一身份）。
 *
 * - phone 全局唯一登录账号
 * - 与租户的关系迁出至 memberships 中间表（Task 7 引入）
 * - is_platform_admin = true → 平台员工，仅 CLI 可置（coffee:bootstrap）
 */
class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasUlid;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'password' => 'hashed',
        'last_login_at' => 'datetime',
        'is_platform_admin' => 'bool',
    ];

    public function platformRole(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Modules\Authorization\Models\PlatformRole::class, 'platform_role_id');
    }

    public function roleBindings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Modules\Authorization\Models\UserRoleBinding::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
