<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->enum('reason_type', [
                'device_unavailable',   // 设备不可用（减少可用数量）
                'insufficient_stock',   // 库存不足
                'invalid_purpose',      // 借用目的不合理
                'time_conflict',        // 时间冲突
                'other'                 // 其他原因
            ])->nullable()->after('reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('reason_type');
        });
    }
};