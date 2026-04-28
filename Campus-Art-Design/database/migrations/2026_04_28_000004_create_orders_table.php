<?php
// 创建订单表
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 32)->notNullable()->unique()->comment('订单编号: M2025042711250001');
            $table->bigInteger('user_id')->unsigned()->notNullable()->comment('用户ID');
            $table->bigInteger('product_id')->unsigned()->notNullable()->comment('商品ID');
            $table->integer('quantity')->notNullable()->default(1)->comment('预订数量');
            $table->string('size_pref', 50)->nullable()->comment('尺寸偏好');
            $table->string('color_pref', 50)->nullable()->comment('颜色偏好');
            $table->string('remark', 500)->nullable()->comment('备注');
            $table->decimal('total_price', 12, 2)->notNullable()->comment('订单总价');
            $table->string('status', 20)->notNullable()->default('draft')->comment('状态');
            $table->tinyInteger('design_status')->default(0)->comment('0:无需定制 1:待上传 2:已上传 3:审核通过 4:审核驳回');
            $table->decimal('paid_amount', 12, 2)->nullable()->comment('实付金额(线下)');
            $table->timestamp('paid_at')->nullable()->comment('支付时间');
            $table->timestamp('completed_at')->nullable()->comment('完成/核销时间');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('product_id')->references('id')->on('products');
            $table->index(['user_id', 'status'], 'idx_user_status');
            $table->index(['product_id', 'status'], 'idx_product_status');
            $table->index('status', 'idx_status');
            $table->index('order_no', 'idx_order_no');
            $table->index('created_at', 'idx_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};