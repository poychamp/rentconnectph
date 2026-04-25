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
            ['name' => 'Water',       'slug' => 'water',       'icon' => 'droplet', 'sort_order' => 1],
            ['name' => 'Electricity', 'slug' => 'electricity', 'icon' => 'bolt',    'sort_order' => 2],
            ['name' => 'WiFi',        'slug' => 'wifi',        'icon' => 'wifi',    'sort_order' => 3],
            ['name' => 'Parking',     'slug' => 'parking',     'icon' => 'car',     'sort_order' => 4],
            ['name' => 'Furnished',   'slug' => 'furnished',   'icon' => 'sofa',    'sort_order' => 5],
        ];

        foreach ($rows as $row) {
            Amenity::create($row);
        }
    }
}
