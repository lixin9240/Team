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
        Schema::create('users', function (Blueprint $table) {
            $table->id();// 主键
            $table->string('name');// 姓名
            $table->string('email')->unique();// 邮箱
            $table->string('account')->unique();// 账号
            $table->string('phone')->unique();// 手机号
            $table->timestamp('email_verified_at')->nullable();// 邮箱验证时间
            $table->string('password');// 密码
            $table->rememberToken();// 记住我令牌
            $table->timestamps();// 创建时间 更新时间
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();// 邮箱主键
            $table->string('token');// 令牌
            $table->timestamp('created_at')->nullable();// 创建时间
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();// 主键
            $table->foreignId('user_id')->nullable()->index();// 用户ID索引
            $table->string('ip_address', 45)->nullable();// IP地址
            $table->text('user_agent')->nullable();// 用户代理
            $table->longText('payload')->nullable();// 有效载荷
            $table->integer('last_activity')->index();// 最后活动时间索引
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');// 删除用户表
        Schema::dropIfExists('password_reset_tokens');// 删除密码重置令牌表
        Schema::dropIfExists('sessions');// 删除会话表
    }
};