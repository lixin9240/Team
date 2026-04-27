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
        Schema::create('devices', function (Blueprint $table) {
            // 基础字段
            $table->id();
            $table->string('name', 100)->nullable(false); // 设备名称
            $table->string('category', 50)->nullable(false); // 设备分类
            $table->text('description')->nullable(true); // 设备描述
            $table->unsignedInteger('total_qty')->default(1)->nullable(false); // 总库存
            $table->unsignedInteger('available_qty')->default(1)->nullable(false); // 可借数量
            $table->enum('status', ['available', 'borrowed', 'maintenance'])->default('available')->nullable(false); // 设备状态：可借、已借出、维修中

            // 时间戳 & 软删除
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate();
            $table->timestamp('deleted_at')->nullable(true);

            // 索引
            $table->index('name'); // 设备名称搜索
            $table->index('category'); // 按分类筛选
            $table->index('status'); // 状态筛选
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
