<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Device extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'devices';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'name',// 设备名称
        'category',// 设备分类
        'description',// 设备描述
        'total_qty',// 总库存
        'available_qty',// 可借数量
        'status',// 设备状态
        'realtime_available_qty',// 实时可借数量（视图计算字段）
    ];

    protected $casts = [
        'total_qty' => 'integer',
        'available_qty' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

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

    // 状态常量
    const STATUS_AVAILABLE = 'available';
    const STATUS_MAINTENANCE = 'maintenance';

    /**
     * 设备借用记录
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

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
     * 是否有库存
     */
    public function inStock(): bool
    {
        return $this->available_qty > 0;
    }

    /**
     * 模型启动时注册事件
     */
    protected static function boot()
    {
        parent::boot();

        // 创建或更新前验证分类是否存在
        static::saving(function ($device) {
            if ($device->isDirty('category')) {
                $category = \App\Models\Category::where('code', $device->category)->first();
                if (!$category) {
                    throw new \Exception("设备分类 '{$device->category}' 不存在，请先创建分类");
                }
            }
        });
    }
}