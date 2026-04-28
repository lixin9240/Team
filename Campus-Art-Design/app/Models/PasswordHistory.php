<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'password', 'created_at'])]
class PasswordHistory extends Model
{
    use HasFactory;

    protected $table = 'password_histories';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}