<?php
// 订单附件模型
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderAttachment extends Model
{
    use HasFactory;

    protected $table = 'order_attachments';

    protected $fillable = [
        'order_id',
        'file_url',
        'file_name',
        'file_size',
        'mime_type',
        'width',
        'height',
        'is_deleted',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'is_deleted' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}