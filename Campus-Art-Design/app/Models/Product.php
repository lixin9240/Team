<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'category_id', 'type', 'spec', 'price', 'stock', 'reserved_qty', 'cover_url', 'custom_rule', 'status', 'version'])]
class Product extends Model
{
    use HasFactory;

    protected $table = 'products';

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
            'reserved_qty' => 'integer',
            'status' => 'integer',
            'version' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }
}