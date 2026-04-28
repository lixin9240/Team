<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->tinyInteger('role')->default(1)->after('password')->comment('1:用户 2:管理员');
            $table->string('phone', 20)->nullable()->after('role')->comment('手机号');
            $table->index('role', 'idx_role');
            $table->index('email', 'idx_email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_role');
            $table->dropIndex('idx_email');
            $table->dropColumn(['role', 'phone']);
        });
    }
};