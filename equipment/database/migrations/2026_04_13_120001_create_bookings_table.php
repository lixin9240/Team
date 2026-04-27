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
        Schema::create('bookings', function (Blueprint $table) {
            // 基础字段
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable(false); // 关联用户
            $table->unsignedBigInteger('device_id')->nullable(false); // 关联设备
            $table->string('device_name', 100)->nullable()->comment('设备名称（方便查询）');
            $table->date('borrow_start')->nullable(false); // 借用开始日期
            $table->date('borrow_end')->nullable(false); // 借用结束日期
            $table->text('purpose')->nullable(true); // 借用用途
            $table->string('status', 20)->default('pending')->nullable(false); // 申请状态：pending(待审核)、approved(已通过)、rejected(已拒绝)、returning(申请归还)、returned(已归还)、return_rejected(拒绝归还)
            $table->string('reason')->nullable(true); // 拒绝原因

            // 时间戳 & 软删除
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate();
            $table->timestamp('deleted_at')->nullable(true);

            // 外键约束
            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade'); // 用户删除则级联删除记录
            $table->foreign('device_id')
                  ->references('id')->on('devices')
                  ->onDelete('cascade'); // 设备删除则级联删除记录

            // 索引
            $table->index('user_id'); // 个人借用记录查询
            $table->index('device_id'); // 设备借用记录查询
            $table->index('status'); // 状态筛选
            $table->index(['user_id', 'device_id', 'status']); // 借用冲突校验复合索引
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 先删外键再删表，避免约束报错
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['device_id']);
        });
        Schema::dropIfExists('bookings');
    }
};
