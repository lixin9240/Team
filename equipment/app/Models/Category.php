<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'categories';

    protected $fillable = [
        'name',        // 分类名称
        'code',        // 分类编码
        'description', // 分类描述
        'sort_order',  // 排序
        'is_active',   // 是否启用
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

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
     * 关联设备
     */
    public function devices()
    {
        return $this->hasMany(Device::class, 'category', 'code');
    }

    /**
     * 作用域：启用的分类
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * 作用域：按排序
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('id', 'asc');
    }
}