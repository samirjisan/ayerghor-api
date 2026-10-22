<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ShopDemoSeeder extends Seeder
{
    /** Demo categories/products so the landing page is not empty. */
    public function run(): void
    {
        $cats = [
            'furniture'   => 'Furniture',
            'kitchen'     => 'Kitchen',
            'home-decor'  => 'Home Decor',
            'electronics' => 'Electronics',
        ];

        foreach ($cats as $slug => $name) {
            Category::updateOrCreate(['slug' => $slug], ['name' => $name]);
        }

        $products = [
            ['furniture',   'Wooden Study Table',  8500, 7900, 12],
            ['furniture',   'Comfort Sofa Chair',  14500, null, 6],
            ['kitchen',     'Non-stick Cookware Set', 3200, 2800, 25],
            ['kitchen',     'Rice Cooker 1.8L',    2400, null, 30],
            ['home-decor',  'Wall Clock Classic',  950, 850, 40],
            ['home-decor',  'Table Lamp Warm',     1300, null, 18],
            ['electronics', 'Ceiling Fan 56"',     4200, 3900, 20],
            ['electronics', 'LED Bulb Pack (4)',   520, null, 100],
        ];

        foreach ($products as [$cat, $name, $price, $sale, $stock]) {
            Product::updateOrCreate(
                ['slug' => \Illuminate\Support\Str::slug($name)],
                [
                    'category_id' => Category::where('slug', $cat)->value('id'),
                    'name'        => $name,
                    'price'       => $price,
                    'sale_price'  => $sale,
                    'stock'       => $stock,
                    'is_featured' => true,
                    'is_active'   => true,
                ]
            );
        }
    }
}
