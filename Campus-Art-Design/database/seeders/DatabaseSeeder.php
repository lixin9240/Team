<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 创建管理员用户
        User::factory()->create([
            'name' => '管理员',
            'email' => 'admin@example.com',
            'account' => 'admin',
            'password' => 'admin@123',
            'phone' => '13800138001',
            'role' => 'admin',
            'email_verified_at' => null,
        ]);

        // 创建普通用户1
        User::factory()->create([
            'name' => '普通用户1',
            'email' => 'user1@example.com',
            'account' => 'user1',
            'password' => 'user1@123',
            'phone' => '13800138002',
            'role' => 'user',
            'email_verified_at' => null,
        ]);

        // 创建普通用户2
        User::factory()->create([
            'name' => '普通用户2',
            'email' => 'user2@example.com',
            'account' => 'user2',
            'password' => 'user2@123',
            'phone' => '13800138003',
            'role' => 'user',
            'email_verified_at' => null,
        ]);
    }
}
