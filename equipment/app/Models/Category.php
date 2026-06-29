<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    // ─── 数据库表映射 ──────────────────────────────

    protected $table = 'categories';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    // ─── 批量赋值字段 ──────────────────────────────

    protected $fillable = [
        'name',        // 分类名称
        'code',        // 分类编码
        'description', // 分类描述
        'sort_order',  // 排序
        'is_active',   // 是否启用
    ];

    // ─── 数据类型转换 ──────────────────────────────

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
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

    // ─── 关联关系 ──────────────────────────────────

    /**
     * 关联设备
     */
    public function devices()
    {
        return $this->hasMany(Device::class, 'category', 'code');
    }

    // ─── 查询作用域 ────────────────────────────────

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

    /**
     * 作用域：按分类编码筛选
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }
}
