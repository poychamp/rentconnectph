<?php

namespace Database\Seeders;

use App\Models\Listing;
use App\Models\ListingImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ListingSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('listing_images')->truncate();
        DB::table('listings')->truncate();
        Schema::enableForeignKeyConstraints();

        $u = fn (string $id) => "https://images.unsplash.com/photo-{$id}?auto=format&fit=crop&w=800&h=533&q=80";

        $rows = [
            ['title' => 'The Garden Suites',      'type' => 'apartment', 'price_monthly' => 12500, 'beds' => 2, 'baths' => 1, 'sqft' => 45,  'barangay' => 'kauswagan',     'is_featured' => true,  'verified_at' => Carbon::now()->subDays(6), 'image' => $u('1522708323590-d24dbb6b0267')],
            ['title' => 'Pueblo Loft 12B',        'type' => 'studio',    'price_monthly' => 18000, 'beds' => 1, 'baths' => 1, 'sqft' => 32,  'barangay' => 'pueblo_de_oro', 'is_featured' => true,  'verified_at' => Carbon::now()->subDays(5), 'image' => $u('1560448204-e02f11c3d0e2')],
            ['title' => 'Uptown Family House',    'type' => 'house',     'price_monthly' => 35000, 'beds' => 4, 'baths' => 3, 'sqft' => 180, 'barangay' => 'uptown',        'is_featured' => true,  'verified_at' => Carbon::now()->subDays(4), 'image' => $u('1564013799919-ab600027ffc6')],
            ['title' => 'Carmen Tower 8F',        'type' => 'condo',     'price_monthly' => 22000, 'beds' => 2, 'baths' => 2, 'sqft' => 60,  'barangay' => 'carmen',        'is_featured' => false, 'verified_at' => Carbon::now()->subDays(1), 'image' => $u('1545324418-cc1a3fa10c00')],
            ['title' => 'Nazareth Bedspacer',     'type' => 'bedspacer', 'price_monthly' => 4500,  'beds' => 1, 'baths' => 1, 'sqft' => 12,  'barangay' => 'nazareth',      'is_featured' => false, 'verified_at' => Carbon::now()->subDays(2), 'image' => $u('1505691938895-1758d7feb511')],
            ['title' => 'Lapasan Compact Studio', 'type' => 'studio',    'price_monthly' => 9800,  'beds' => 1, 'baths' => 1, 'sqft' => 24,  'barangay' => 'lapasan',       'is_featured' => false, 'verified_at' => Carbon::now()->subDays(3), 'image' => $u('1554995207-c18c203602cb')],
            ['title' => 'Indahag Hillside Home',  'type' => 'house',     'price_monthly' => 28000, 'beds' => 3, 'baths' => 2, 'sqft' => 140, 'barangay' => 'indahag',       'is_featured' => false, 'verified_at' => Carbon::now()->subDays(4), 'image' => $u('1568605114967-8130f3a36994')],
            ['title' => 'Macasandig 2-BR Flat',   'type' => 'apartment', 'price_monthly' => 16500, 'beds' => 2, 'baths' => 1, 'sqft' => 55,  'barangay' => 'macasandig',    'is_featured' => false, 'verified_at' => Carbon::now()->subDays(5), 'image' => $u('1502672260266-1c1ef2d93688')],
        ];

        $extraImages = [
            $u('1502672260266-1c1ef2d93688'),
            $u('1554995207-c18c203602cb'),
            $u('1568605114967-8130f3a36994'),
            $u('1505691938895-1758d7feb511'),
            $u('1545324418-cc1a3fa10c00'),
        ];

        foreach ($rows as $i => $row) {
            $listing = Listing::create([
                'title' => $row['title'],
                'type' => $row['type'],
                'price_monthly' => $row['price_monthly'],
                'beds' => $row['beds'],
                'baths' => $row['baths'],
                'sqft' => $row['sqft'],
                'barangay' => $row['barangay'],
                'is_verified' => true,
                'verified_at' => $row['verified_at'],
                'is_featured' => $row['is_featured'],
            ]);

            $primary = ListingImage::create([
                'listing_id' => $listing->id,
                'url' => $row['image'],
                'sort_order' => 0,
            ]);

            ListingImage::create([
                'listing_id' => $listing->id,
                'url' => $extraImages[$i % count($extraImages)],
                'sort_order' => 1,
            ]);

            ListingImage::create([
                'listing_id' => $listing->id,
                'url' => $extraImages[($i + 2) % count($extraImages)],
                'sort_order' => 2,
            ]);

            $listing->update(['display_image_id' => $primary->id]);
        }
    }
}
