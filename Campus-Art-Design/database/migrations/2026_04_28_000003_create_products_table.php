<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200)->notNullable()->comment('商品名称');
            $table->bigInteger('category_id')->unsigned()->notNullable()->comment('分类ID');
            $table->string('type', 50)->notNullable()->comment('类型:文创/物料');
            $table->string('spec', 500)->nullable()->comment('规格说明');
            $table->decimal('price', 10, 2)->notNullable()->comment('单价');
            $table->integer('stock')->notNullable()->default(0)->comment('总库存');
            $table->integer('reserved_qty')->notNullable()->default(0)->comment('已预留库存');
            $table->string('cover_url', 500)->nullable()->comment('封面图OSS地址');
            $table->text('custom_rule')->nullable()->comment('定制要求说明');
            $table->tinyInteger('status')->notNullable()->default(1)->comment('0:下架 1:上架 2:售罄');
            $table->integer('version')->notNullable()->default(0)->comment('乐观锁版本号');
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('product_categories');
            $table->index(['category_id', 'status'], 'idx_category_status');
            $table->index('price', 'idx_price');
            $table->index(['status', 'type'], 'idx_status_type');
            $table->index('name', 'idx_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};