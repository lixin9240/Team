<?php

namespace App\Models;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Device extends Model
{
    use HasFactory, SoftDeletes;

    // ─── 数据库表映射 ──────────────────────────────

    protected $table = 'devices';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    // ─── 批量赋值字段 ──────────────────────────────

    protected $fillable = [
        'name',          // 设备名称
        'category',      // 设备分类
        'description',   // 设备描述
        'total_qty',     // 总库存
        'available_qty', // 可借数量
        'status',        // 设备状态
    ];

    // ─── 数据类型转换 ──────────────────────────────

    protected $casts = [
        'total_qty'     => 'integer',
        'available_qty' => 'integer',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
        'deleted_at'    => 'datetime',
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

    // ─── 状态常量 ──────────────────────────────────

    const STATUS_AVAILABLE = 'available';     // 可借用
    const STATUS_MAINTENANCE = 'maintenance'; // 维护中

    // ─── 关联关系 ──────────────────────────────────

    /**
     * 设备借用记录
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * 关联分类
     */
    public function categoryInfo()
    {
        return $this->belongsTo(Category::class, 'category', 'code');
    }

    // ─── 查询作用域 ────────────────────────────────

    /**
     * 作用域：可借用
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', self::STATUS_AVAILABLE);
    }

    /**
     * 作用域：维护中
     */
    public function scopeMaintenance($query)
    {
        return $query->where('status', self::STATUS_MAINTENANCE);
    }

    /**
     * 作用域：有库存（available_qty > 0）
     */
    public function scopeInStock($query)
    {
        return $query->where('available_qty', '>', 0);
    }

    /**
     * 作用域：按分类编码筛选
     */
    public function scopeByCategory($query, string $categoryCode)
    {
        return $query->where('category', $categoryCode);
    }

    // ─── 业务方法 ──────────────────────────────────

    /**
     * 是否有库存
     */
    public function inStock(): bool
    {
        return $this->available_qty > 0;
    }

    // ─── 模型事件 ──────────────────────────────────

    /**
     * 模型启动时注册事件
     */
    protected static function boot()
    {
        parent::boot();

        // 创建或更新前验证分类是否存在
        static::saving(function ($device) {
            if ($device->isDirty('category')) {
                $category = Category::where('code', $device->category)->first();
                if (! $category) {
                    throw new BusinessException("设备分类 '{$device->category}' 不存在，请先创建分类", ResponseCode::DATA_NOT_FOUND);
                }
            }
        });
    }
}
