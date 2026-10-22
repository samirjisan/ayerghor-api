<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'furniture' => 'Furniture',
            'kitchen' => 'Kitchen',
            'home-decor' => 'Home Decor',
            'electronics' => 'Electronics',
        ] as $slug => $name) {
            DB::table('categories')->where('slug', $slug)->update(['name' => $name]);
        }
    }

    public function down(): void
    {
        foreach ([
            'furniture' => 'আসবাবপত্র',
            'kitchen' => 'রান্নাঘর',
            'home-decor' => 'ঘর সাজানো',
            'electronics' => 'ইলেকট্রনিক্স',
        ] as $slug => $name) {
            DB::table('categories')->where('slug', $slug)->update(['name' => $name]);
        }
    }
};
