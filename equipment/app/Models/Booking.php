<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    // ─── 数据库表映射 ──────────────────────────────

    protected $table = 'bookings';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    // ─── 批量赋值字段 ──────────────────────────────

    protected $fillable = [
        'user_id',       // 用户ID
        'device_id',     // 设备ID
        'device_name',   // 设备名称（冗余字段，用于快照）
        'borrow_start',  // 借用开始日期
        'borrow_end',    // 借用结束日期
        'purpose',       // 借用目的
        'status',        // 状态
        'reason',        // 拒绝原因
        'reason_type',   // 拒绝类型
    ];

    // ─── 数据类型转换 ──────────────────────────────

    protected $casts = [
        'borrow_start' => 'date',
        'borrow_end'   => 'date',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
        'deleted_at'   => 'datetime',
    ];

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

    public function getBorrowStartAttribute($value)
    {
        return $value ? \Carbon\Carbon::parse($value)->timezone('Asia/Shanghai')->format('Y-m-d') : null;
    }

    public function getBorrowEndAttribute($value)
    {
        return $value ? \Carbon\Carbon::parse($value)->timezone('Asia/Shanghai')->format('Y-m-d') : null;
    }

    // ─── 状态常量 ──────────────────────────────────

    const STATUS_PENDING = 'pending';              // 待审核
    const STATUS_APPROVED = 'approved';            // 已通过
    const STATUS_REJECTED = 'rejected';            // 已拒绝
    const STATUS_RETURNING = 'returning';          // 申请归还（待审核）
    const STATUS_RETURNED = 'returned';            // 已归还
    const STATUS_RETURN_REJECTED = 'return_rejected'; // 拒绝归还

    // ─── 关联关系 ──────────────────────────────────

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

    // ─── 查询作用域 ────────────────────────────────

    /**
     * 作用域：按用户ID筛选
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * 作用域：按设备ID筛选
     */
    public function scopeByDevice($query, int $deviceId)
    {
        return $query->where('device_id', $deviceId);
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

    /**
     * 作用域：归还被拒绝
     */
    public function scopeReturnRejected($query)
    {
        return $query->where('status', self::STATUS_RETURN_REJECTED);
    }

    /**
     * 作用域：按借用日期范围筛选
     */
    public function scopeBetweenDates($query, string $start, string $end)
    {
        return $query->where('borrow_start', '>=', $start)
                     ->where('borrow_end', '<=', $end);
    }

    /**
     * 作用域：逾期未归还（借用结束日期已过且状态仍为已批准）
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_APPROVED)
                     ->where('borrow_end', '<', now()->toDateString());
    }
}
