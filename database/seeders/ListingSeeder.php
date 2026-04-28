<?php

namespace Database\Seeders;

use App\Models\Amenity;
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
        DB::table('amenity_listing')->truncate();
        DB::table('listings')->truncate();
        Schema::enableForeignKeyConstraints();

        $u = fn (string $id) => "https://images.unsplash.com/photo-{$id}?auto=format&fit=crop&w=800&h=533&q=80";

        $amenityIds = Amenity::pluck('id', 'slug');

        $rows = [
            [
                'title' => 'The Garden Suites',
                'type' => 'apartment',
                'price_monthly' => 12500,
                'beds' => 2, 'baths' => 1, 'sqm' => 45,
                'barangay' => 'kauswagan',
                'latitude' => 8.4862,
                'longitude' => 124.6175,
                'is_featured' => true,
                'verified_at' => Carbon::now()->subDays(6),
                'image' => $u('1522708323590-d24dbb6b0267'),
                'amenity_slugs' => ['water', 'electricity', 'wifi', 'parking', 'furnished'],
                'description' => "Quiet 2-bedroom apartment in Kauswagan with a small garden balcony, fast-cooling AC, and a working desk built-in.\n\nThe building has a friendly long-term tenant mix and a 24/7 guard at the gate. Walking distance to Limketkai Center and the Cogon market — easy weekday trips, easy weekend escapes.",
            ],
            [
                'title' => 'Pueblo Loft 12B',
                'type' => 'studio',
                'price_monthly' => 18000,
                'beds' => 1, 'baths' => 1, 'sqm' => 32,
                'barangay' => 'pueblo_de_oro',
                'latitude' => 8.4707,
                'longitude' => 124.6608,
                'is_featured' => true,
                'verified_at' => Carbon::now()->subDays(5),
                'image' => $u('1560448204-e02f11c3d0e2'),
                'amenity_slugs' => ['water', 'electricity', 'wifi', 'furnished'],
                'description' => "High-floor studio loft inside Pueblo de Oro Townscapes with sweeping city views and a real bathtub.\n\nFloor-to-ceiling windows on the south wall — golden-hour light is unreasonable. Building has elevator, generator, and an outdoor pool you'll forget you have access to.",
            ],
            [
                'title' => 'Uptown Family House',
                'type' => 'house',
                'price_monthly' => 35000,
                'beds' => 4, 'baths' => 3, 'sqm' => 180,
                'barangay' => 'uptown',
                'latitude' => 8.4781,
                'longitude' => 124.6491,
                'is_featured' => true,
                'verified_at' => Carbon::now()->subDays(4),
                'image' => $u('1564013799919-ab600027ffc6'),
                'amenity_slugs' => ['water', 'electricity', 'wifi', 'parking'],
                'description' => "4-bedroom family home in Uptown with a private gate, two-car carport, and a small fenced yard out back.\n\nMaster bedroom has its own en-suite. Roof deck rough-in is wired but not finished — the owner left it for the next family to make their own.",
            ],
            [
                'title' => 'Carmen Tower 8F',
                'type' => 'condo',
                'price_monthly' => 22000,
                'beds' => 2, 'baths' => 2, 'sqm' => 60,
                'barangay' => 'carmen',
                'latitude' => 8.4733,
                'longitude' => 124.6285,
                'is_featured' => false,
                'verified_at' => Carbon::now()->subDays(1),
                'image' => $u('1545324418-cc1a3fa10c00'),
                'amenity_slugs' => ['water', 'electricity', 'wifi', 'parking', 'furnished'],
                'description' => "Modern 2-bedroom condo unit on the 8th floor of Carmen Tower, fully furnished and walking distance to SM CDO and Centrio.\n\nUnit is move-in ready: Smart TV, washer-dryer combo, induction stove. Building has 24-hour reception and rooftop pool. Great for couples or a single professional.",
            ],
            [
                'title' => 'Nazareth Bedspacer',
                'type' => 'bedspacer',
                'price_monthly' => 4500,
                'beds' => 1, 'baths' => 1, 'sqm' => 12,
                'barangay' => 'nazareth',
                'latitude' => 8.4775,
                'longitude' => 124.6450,
                'is_featured' => false,
                'verified_at' => Carbon::now()->subDays(2),
                'image' => $u('1505691938895-1758d7feb511'),
                'amenity_slugs' => ['water', 'electricity', 'wifi'],
                'description' => "Single bedspacer slot in a quiet Nazareth shared house — great for students or fresh hires keeping rent tight.\n\nCommon kitchen, two shared bathrooms, fast WiFi included. Curfew is 11pm, building is mostly female tenants. Walking distance to Xavier University and the Pueblo jeep route.",
            ],
            [
                'title' => 'Lapasan Compact Studio',
                'type' => 'studio',
                'price_monthly' => 9800,
                'beds' => 1, 'baths' => 1, 'sqm' => 24,
                'barangay' => 'lapasan',
                'latitude' => 8.4828,
                'longitude' => 124.6517,
                'is_featured' => false,
                'verified_at' => Carbon::now()->subDays(3),
                'image' => $u('1554995207-c18c203602cb'),
                'amenity_slugs' => ['water', 'electricity', 'wifi', 'furnished'],
                'description' => "Compact studio close to Lapasan port and the night markets. Loft-style ceiling with a Murphy bed — small footprint, big breathing room.\n\nIncluded: bed, fridge, gas stove, hot shower. Lapasan is the food-tour barangay of CDO and you'll feel that within a week of moving in.",
            ],
            [
                'title' => 'Indahag Hillside Home',
                'type' => 'house',
                'price_monthly' => 28000,
                'beds' => 3, 'baths' => 2, 'sqm' => 140,
                'barangay' => 'indahag',
                'latitude' => 8.4233,
                'longitude' => 124.6489,
                'is_featured' => false,
                'verified_at' => Carbon::now()->subDays(4),
                'image' => $u('1568605114967-8130f3a36994'),
                'amenity_slugs' => ['water', 'electricity', 'parking'],
                'description' => "3-bedroom hillside home with mountain views from the balcony, inside a gated subdivision in Indahag.\n\nFurnished kitchen, real fireplace (Indahag gets crisp December nights), two-car driveway, dog-friendly. About 15 minutes by car to the city center — quiet, but not isolated.",
            ],
            [
                'title' => 'Macasandig 2-BR Flat',
                'type' => 'apartment',
                'price_monthly' => 16500,
                'beds' => 2, 'baths' => 1, 'sqm' => 55,
                'barangay' => 'macasandig',
                'latitude' => null,
                'longitude' => null,
                'is_featured' => false,
                'verified_at' => Carbon::now()->subDays(5),
                'image' => $u('1502672260266-1c1ef2d93688'),
                'amenity_slugs' => ['water', 'electricity', 'wifi'],
                'description' => "Cozy 2-bedroom flat in Macasandig — quiet residential street with corner stores and groceries within a 3-minute walk.\n\nMid-rise building, second floor, small balcony off the living room. Owner is responsive and keeps rent stable for long-term tenants.",
            ],
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
                'sqm' => $row['sqm'],
                'barangay' => $row['barangay'],
                'latitude' => $row['latitude'],
                'longitude' => $row['longitude'],
                'description' => $row['description'],
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

            $ids = collect($row['amenity_slugs'])
                ->map(fn ($slug) => $amenityIds[$slug] ?? null)
                ->filter()
                ->values()
                ->all();

            if (! empty($ids)) {
                $listing->amenities()->attach($ids);
            }
        }
    }
}
