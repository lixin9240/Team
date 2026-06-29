<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, SoftDeletes, HasApiTokens;

    // ─── 数据库表映射 ──────────────────────────────

    protected $table = 'users';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    // ─── 批量赋值字段 ──────────────────────────────

    protected $fillable = [
        'account',  // 账号
        'name',     // 姓名
        'password', // 密码
        'role',     // 角色（学生 / 管理员）
        'phone',    // 手机号
        'email',    // 邮箱
        'avatar',   // 头像
    ];

    // ─── 隐藏字段 ──────────────────────────────────

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // ─── 数据类型转换 ──────────────────────────────

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ─── 修改器 ────────────────────────────────────

    /**
     * 设置密码时自动哈希加密
     */
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = Hash::make($value);
    }

    // ─── 时间访问器（北京时间格式化） ───────────────

    public function getCreatedAtAttribute($value)
    {
        return $value ? \Carbon\Carbon::parse($value)->timezone('Asia/Shanghai')->format('Y-m-d H:i:s') : null;
    }

    public function getUpdatedAtAttribute($value)
    {
        return $value ? \Carbon\Carbon::parse($value)->timezone('Asia/Shanghai')->format('Y-m-d H:i:s') : null;
    }

    public function getDeletedAtAttribute($value)
    {
        return $value ? \Carbon\Carbon::parse($value)->timezone('Asia/Shanghai')->format('Y-m-d H:i:s') : null;
    }

    // ─── 角色常量 ──────────────────────────────────

    const ROLE_STUDENT = 'student'; // 学生
    const ROLE_ADMIN = 'admin';     // 管理员

    // ─── 关联关系 ──────────────────────────────────

    /**
     * 用户借用记录
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    // ─── 查询作用域 ────────────────────────────────

    /**
     * 作用域：学生
     */
    public function scopeStudent($query)
    {
        return $query->where('role', self::ROLE_STUDENT);
    }

    /**
     * 作用域：管理员
     */
    public function scopeAdmin($query)
    {
        return $query->where('role', self::ROLE_ADMIN);
    }

    // ─── 业务方法 ──────────────────────────────────

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

    // ─── JWT 接口实现 ──────────────────────────────

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
}
