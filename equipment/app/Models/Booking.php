<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'bookings';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'user_id',// 用户ID
        'device_id',// 设备ID
        'device_name',// 设备名称（冗余字段）
        'borrow_start',// 借用开始日期
        'borrow_end',// 借用结束日期
        'purpose',// 借用目的
        'status',// 状态
        'reason',// 拒绝原因
        'reason_type',// 拒绝类型
    ];

    protected $casts = [ // 类型转换
        'borrow_start' => 'date',
        'borrow_end' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $dates = ['borrow_start', 'borrow_end', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * 获取创建时间（北京时间）
     */
    public function getCreatedAtAttribute($value)
    {
        return $value ? \Carbon\Carbon::parse($value)->timezone('Asia/Shanghai')->format('Y-m-d H:i:s') : null;
    }

    /**
     * 获取更新时间（北京时间）
     */
    public function getUpdatedAtAttribute($value)
    {
        return $value ? \Carbon\Carbon::parse($value)->timezone('Asia/Shanghai')->format('Y-m-d H:i:s') : null;
    }

    /**
     * 获取删除时间（北京时间）
     */
    public function getDeletedAtAttribute($value)
    {
        return $value ? \Carbon\Carbon::parse($value)->timezone('Asia/Shanghai')->format('Y-m-d H:i:s') : null;
    }

    /**
     * 获取借用开始日期（北京时间）
     */
    public function getBorrowStartAttribute($value)
    {
        return $value ? \Carbon\Carbon::parse($value)->timezone('Asia/Shanghai')->format('Y-m-d') : null;
    }

    /**
     * 获取借用结束日期（北京时间）
     */
    public function getBorrowEndAttribute($value)
    {
        return $value ? \Carbon\Carbon::parse($value)->timezone('Asia/Shanghai')->format('Y-m-d') : null;
    }

    // 状态常量
    const STATUS_PENDING = 'pending';           // 待审核
    const STATUS_APPROVED = 'approved';         // 已通过
    const STATUS_REJECTED = 'rejected';         // 已拒绝
    const STATUS_RETURNING = 'returning';       // 申请归还（待审核）
    const STATUS_RETURNED = 'returned';         // 已归还
    const STATUS_RETURN_REJECTED = 'return_rejected';  // 拒绝归还

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 关联设备
     */
    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * 作用域：待审核
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * 作用域：已批准
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * 作用域：已拒绝
     */
    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    /**
     * 作用域：申请归还（待审核）
     */
    public function scopeReturning($query)
    {
        return $query->where('status', self::STATUS_RETURNING);
    }

    /**
     * 作用域：已归还
     */
    public function scopeReturned($query)
    {
        return $query->where('status', self::STATUS_RETURNED);
    }
}