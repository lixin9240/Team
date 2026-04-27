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
        Schema::create('users', function (Blueprint $table) {
            // 基础字段
            $table->id();
            $table->string('account', 50)->unique(); // 唯一索引：登录账号
            $table->string('name', 30)->nullable(false); // 真实姓名
            $table->string('password', 255)->nullable(false); 
            $table->enum('role', ['student', 'admin'])->nullable(false); // 角色
            $table->string('phone', 20)->nullable(true)->unique(); // 手机号
            $table->string('email', 100)->nullable(true); // 邮箱
            $table->string('remember_token', 100)->nullable(true); // Laravel记住我

            // 时间戳 & 软删除
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate();
            $table->timestamp('deleted_at')->nullable(true);

            // 索引
            $table->index('role'); // 按角色筛选索引
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
