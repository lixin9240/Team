<?php
// 创建审核日志表
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();// 主键
            $table->bigInteger('order_id')->unsigned()->notNullable()->comment('订单ID');
            $table->bigInteger('operator_id')->unsigned()->notNullable()->comment('操作人ID');
            $table->string('action', 50)->notNullable()->comment('操作类型');
            $table->string('from_status', 20)->nullable()->comment('原状态');
            $table->string('to_status', 20)->nullable()->comment('目标状态');
            $table->string('remark', 500)->nullable()->comment('操作备注');
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders');// 订单ID外键
            $table->foreign('operator_id')->references('id')->on('users');// 操作人ID外键
            $table->index('order_id', 'idx_order_id');// 订单ID索引
            $table->index('operator_id', 'idx_operator_id');// 操作人ID索引
            $table->index('created_at', 'idx_created_at');// 创建时间索引
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');// 删除审核日志表
    }
};