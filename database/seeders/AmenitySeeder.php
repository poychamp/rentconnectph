<?php

namespace Database\Seeders;

use App\Models\Amenity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AmenitySeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('amenity_listing')->truncate();
        DB::table('amenities')->truncate();
        Schema::enableForeignKeyConstraints();

        $rows = [
            ['name' => 'WiFi',       'slug' => 'wifi',       'icon' => 'wifi',       'sort_order' => 1],
            ['name' => 'Parking',    'slug' => 'parking',    'icon' => 'car',        'sort_order' => 2],
            ['name' => 'Smoking',    'slug' => 'smoking',    'icon' => 'cigarette',  'sort_order' => 3],
            ['name' => 'No Smoking', 'slug' => 'no_smoking', 'icon' => 'no_smoking', 'sort_order' => 4],
            ['name' => 'Pets',       'slug' => 'pets',       'icon' => 'pets',       'sort_order' => 5],
            ['name' => 'No Pets',    'slug' => 'no_pets',    'icon' => 'no_pets',    'sort_order' => 6],
        ];

        foreach ($rows as $row) {
            Amenity::create($row);
        }
    }
}
