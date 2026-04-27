<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => '电脑设备',
                'code' => '电脑设备',
                'description' => '笔记本电脑、台式机、平板电脑等',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => '摄影摄像',
                'code' => '摄影摄像',
                'description' => '相机、摄像机、无人机等',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => '音频设备',
                'code' => '音频设备',
                'description' => '麦克风、耳机、音响等',
                'sort_order' => 3,
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                ['code' => $category['code']],
                $category
            );
        }
    }
}