<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['order_no', 'user_id', 'product_id', 'quantity', 'size_pref', 'color_pref', 'remark', 'total_price', 'status', 'design_status', 'paid_amount', 'paid_at', 'completed_at'])]
class Order extends Model
{
    use HasFactory;

    protected $table = 'orders';

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'total_price' => 'decimal:2',
            'design_status' => 'integer',
            'paid_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(\App\Models\OrderAttachment::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(\App\Models\AuditLog::class);
    }
}