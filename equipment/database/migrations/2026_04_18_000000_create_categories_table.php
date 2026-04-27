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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->nullable(false)->comment('分类名称');
            $table->string('code', 50)->nullable(false)->unique()->comment('分类编码');
            $table->string('description', 255)->nullable()->comment('分类描述');
            $table->unsignedInteger('sort_order')->default(0)->comment('排序');
            $table->boolean('is_active')->default(true)->comment('是否启用');

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate();
            $table->timestamp('deleted_at')->nullable();

            // 索引
            $table->index('code');
            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};