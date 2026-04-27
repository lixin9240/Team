<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;


class User extends Authenticatable implements JWTSubject
{
    use HasFactory, SoftDeletes, HasApiTokens;


    protected $table = 'users';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'account',// 账号
        'name',// 姓名
        'password',// 密码
        'role',// 角色（学生 / 管理员）
        'phone',// 手机号
        'email',// 邮箱
        'avatar',// 头像
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    /**
     * 设置密码时自动哈希加密
     */
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = Hash::make($value);
    }

    /**
     * 获取创建时间（北京时间）
     */
    public function getCreatedAtAttribute($value)
    {
        return $value ? \Carbon\Carbon::parse($value)->timezone('Asia/Shanghai') : null;
    }

    /**
     * 获取更新时间（北京时间）
     */
    public function getUpdatedAtAttribute($value)
    {
        return $value ? \Carbon\Carbon::parse($value)->timezone('Asia/Shanghai') : null;
    }

    /**
     * 获取删除时间（北京时间）
     */
    public function getDeletedAtAttribute($value)
    {
        return $value ? \Carbon\Carbon::parse($value)->timezone('Asia/Shanghai') : null;
    }

    // 角色常量
    const ROLE_STUDENT = 'student';
    const ROLE_ADMIN = 'admin';

    /**
     * 用户借用记录
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * 作用域：学生
     */
    public function scopeStudent($query)
    {
        return $query->where('role', self::ROLE_STUDENT);
    }

    /**
     * 获取 JWT 标识符
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * 获取 JWT 自定义声明
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * 作用域：管理员
     */
    public function scopeAdmin($query)
    {
        return $query->where('role', self::ROLE_ADMIN);
    }

    /**
     * 是否管理员
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * 是否学生
     */
    public function isStudent(): bool
    {
        return $this->role === self::ROLE_STUDENT;
    }
}
