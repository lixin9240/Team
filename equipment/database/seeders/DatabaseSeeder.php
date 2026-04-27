<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 先填充设备分类
        $this->call(CategorySeeder::class);


        // 创建管理员账号1--LX
        

        // 创建管理员账号2--LZW
        User::firstOrCreate(
            ['account' => 'admin124'],
            [
                'name' => '系统管理员1',
                'password' => 'admin124',
                'role' => 'admin',
                'email' => '193952040@qq.com',
            ]
        );

        // 创建管理员账号--WLJ
        User::firstOrCreate(
            ['account' => 'admin125'],
            [
                'name' => '系统管理员2',
                'password' => 'admin125',
                'role' => 'admin',
                'email' => '2633681826@qq.com',
            ]
        );

      

        // 创建测试学生账号1
        User::firstOrCreate(
            ['account' => '25010420521'],
            [
                'name' => '李四',
                'password' => 'F123457',
                'role' => 'student',
                'email' => '3258599349@qq.com',
            ]
        );
         // 创建测试学生账号2
        User::firstOrCreate(
            ['account' => 'student1'],
            [
                'name' => 'student1',
                'password' => 'student123',
                'role' => 'student',
                'email' => '3258599349@qq.com',
            ]
        );
         // 创建测试学生账号3
        User::firstOrCreate(
            ['account' => 'student2'],
            [
                'name' => 'student2',
                'password' => 'student124',
                'role' => 'student',
                'email' => '3258599349@qq.com',
            ]
        );
        // 创建测试学生账号3
        User::firstOrCreate(
            ['account' => 'student3'],
            [
                'name' => 'student3',
                'password' => 'student125',
                'role' => 'student',
                'email' => '3258599349@qq.com',
            ]
        );
        // 创建测试学生账号4
        User::firstOrCreate(
            ['account' => 'student4'],
            [
                'name' => 'student4',
                'password' => 'student126',
                'role' => 'student',
                'email' => '3258599349@qq.com',
            ]
        );
        // 创建测试学生账号5
        User::firstOrCreate(
            ['account' => 'student5'],
            [
                'name' => 'student5',
                'password' => 'student127',
                'role' => 'student',
                'email' => '3258599349@qq.com',
            ]
        );
        // 创建测试学生账号6
        User::firstOrCreate(
            ['account' => 'student6'],
            [
                'name' => 'student6',
                'password' => 'student128',
                'role' => 'student',
                'email' => '3258599349@qq.com',
            ]
        );
        // 创建测试学生账号7
        User::firstOrCreate(
            ['account' => 'student7'],
            [
                'name' => 'student7',
                'password' => 'student129',
                'role' => 'student',
                'email' => '3258599349@qq.com',
            ]
        );
        // 创建测试学生账号8
        User::firstOrCreate(
            ['account' => 'student8'],
            [
                'name' => 'student8',
                'password' => 'student130',
                'role' => 'student',
                'email' => '3258599349@qq.com',
            ]
        );
        // 创建测试学生账号9
        User::firstOrCreate(
            ['account' => 'student9'],
            [
                'name' => 'student9',
                'password' => 'student131',
                'role' => 'student',
                'email' => '3258599349@qq.com',
            ]
        );
        // 创建测试学生账号10
        User::firstOrCreate(
            ['account' => 'student10'],
            [
                'name' => 'student10',
                'password' => 'student132',
                'role' => 'student',
                'email' => '3258599349@qq.com',
            ]
        );

    } 
    
}
