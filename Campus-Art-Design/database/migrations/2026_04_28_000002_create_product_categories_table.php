<?php
// 创建商品分类表
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();// 主键
            $table->string('name', 50)->notNullable()->comment('分类名称');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->timestamps();// 创建时间、更新时间(北京时间)
            $table->index('sort_order', 'idx_sort');// 排序索引
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_categories');// 删除商品分类表
    }
};