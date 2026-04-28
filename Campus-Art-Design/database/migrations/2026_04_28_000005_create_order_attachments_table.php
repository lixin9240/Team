<?php
// 创建订单附件表
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_attachments', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('order_id')->unsigned()->notNullable()->comment('订单ID');
            $table->string('file_url', 500)->notNullable()->comment('OSS文件路径');
            $table->string('file_name', 255)->notNullable()->comment('原始文件名');
            $table->integer('file_size')->notNullable()->comment('文件大小(字节)');
            $table->string('mime_type', 100)->notNullable()->comment('MIME类型');
            $table->integer('width')->nullable()->comment('图片宽度');
            $table->integer('height')->nullable()->comment('图片高度');
            $table->tinyInteger('is_deleted')->default(0)->comment('0:正常 1:逻辑删除');
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->index('order_id', 'idx_order_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_attachments');
    }
};