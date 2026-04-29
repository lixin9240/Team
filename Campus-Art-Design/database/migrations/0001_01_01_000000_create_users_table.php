<?php
// 创建用户表
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
        // 创建用户表
        Schema::create('users', function (Blueprint $table) {
            $table->id();// 主键
            $table->string('name');// 姓名
            $table->string('email')->unique();// 邮箱
            $table->string('account')->unique();// 账号
            $table->string('phone')->unique();// 手机号
            $table->timestamp('email_verified_at')->nullable();// 邮箱验证时间(北京时间)
            $table->string('password');// 密码
            $table->timestamps();// 创建时间、更新时间(北京时间)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');// 删除用户表
    }
};
