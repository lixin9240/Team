<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $category1 = ProductCategory::create(['name' => '文具', 'sort_order' => 1]);
        $category2 = ProductCategory::create(['name' => '纪念品', 'sort_order' => 2]);
        $category3 = ProductCategory::create(['name' => '服装', 'sort_order' => 3]);

        Product::create([
            'name' => '校园纪念笔记本',
            'category_id' => $category1->id,
            'type' => '文创',
            'spec' => 'A5，硬壳精装',
            'price' => 25.00,
            'stock' => 100,
            'reserved_qty' => 0,
            'cover_url' => 'https://example.com/notebook.jpg',
            'custom_rule' => '可定制封面文字',
            'status' => 1,
            'version' => 0,
        ]);

        Product::create([
            'name' => '校园明信片套装',
            'category_id' => $category2->id,
            'type' => '文创',
            'spec' => '10张/套',
            'price' => 15.00,
            'stock' => 200,
            'reserved_qty' => 0,
            'cover_url' => 'https://example.com/postcard.jpg',
            'custom_rule' => null,
            'status' => 1,
            'version' => 0,
        ]);

        Product::create([
            'name' => '校园文化T恤',
            'category_id' => $category3->id,
            'type' => '物料',
            'spec' => 'S/M/L/XL/XXL',
            'price' => 50.00,
            'stock' => 50,
            'reserved_qty' => 0,
            'cover_url' => 'https://example.com/tshirt.jpg',
            'custom_rule' => '可印制logo和文字',
            'status' => 1,
            'version' => 0,
        ]);
    }
}
