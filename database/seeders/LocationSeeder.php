<?php

namespace Database\Seeders;

use App\Models\District;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * SAMPLE data only. Replace with the full list of 64 districts and all thanas/upazilas
     * (a ready-made bd-geo CSV/JSON can be imported here).
     */
    public function run(): void
    {
        $data = [
            ['name' => 'Dhaka', 'bn_name' => 'ঢাকা', 'thanas' => [
                ['Dhanmondi', 'ধানমন্ডি'], ['Mirpur', 'মিরপুর'], ['Uttara', 'উত্তরা'],
                ['Gulshan', 'গুলশান'], ['Savar', 'সাভার'], ['Keraniganj', 'কেরানীগঞ্জ'],
            ]],
            ['name' => 'Chattogram', 'bn_name' => 'চট্টগ্রাম', 'thanas' => [
                ['Kotwali', 'কোতোয়ালী'], ['Pahartali', 'পাহাড়তলী'], ['Panchlaish', 'পাঁচলাইশ'],
                ['Hathazari', 'হাটহাজারী'],
            ]],
            ['name' => 'Rajshahi', 'bn_name' => 'রাজশাহী', 'thanas' => [
                ['Boalia', 'বোয়ালিয়া'], ['Motihar', 'মতিহার'], ['Paba', 'পবা'],
            ]],
            ['name' => 'Khulna', 'bn_name' => 'খুলনা', 'thanas' => [
                ['Khulna Sadar', 'খুলনা সদর'], ['Sonadanga', 'সোনাডাঙ্গা'], ['Dumuria', 'ডুমুরিয়া'],
            ]],
            ['name' => 'Sylhet', 'bn_name' => 'সিলেট', 'thanas' => [
                ['Kotwali', 'কোতোয়ালী'], ['Jalalabad', 'জালালাবাদ'], ['Beanibazar', 'বিয়ানীবাজার'],
            ]],
        ];

        foreach ($data as $row) {
            $district = District::updateOrCreate(
                ['name' => $row['name']],
                ['bn_name' => $row['bn_name']]
            );

            foreach ($row['thanas'] as [$name, $bn]) {
                $district->thanas()->updateOrCreate(['name' => $name], ['bn_name' => $bn]);
            }
        }
    }
}
