<?php

namespace App\Console\Commands;

use App\Models\Amenity;
use App\Models\Listing;
use App\Models\ListingImage;
use Illuminate\Console\Command;

class RestoreUnverifiedNotCalledListings extends Command
{
    protected $signature = 'listings:restore-unverified-not-called';

    protected $description = 'Temporary: re-seed the rent.ph Uptown CDO listing (unverified, not_called) using hardcoded values.';

    public function handle(): int
    {
        $uuid = '019dda21-efbf-7204-a6e7-763d648802b5';

        $existing = Listing::withTrashed()->where('uuid', $uuid)->first();
        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
                $this->info("Restored existing listing {$uuid}.");
                return self::SUCCESS;
            }
            $this->info("Listing {$uuid} already present — nothing to do.");
            return self::SUCCESS;
        }

        $listing = new Listing();
        $listing->uuid           = $uuid;
        $listing->title          = '3-Bedroom Semi-Furnished House for Rent in Uptown CDO';
        $listing->type           = 'house';
        $listing->price_monthly  = 20000;
        $listing->beds           = 3;
        $listing->baths          = 2;
        $listing->sqm            = 64;
        $listing->barangay       = 'uptown';
        $listing->description    = "Semi-furnished end unit in Montierra Subdivision, a gated community in Uptown CDO. Includes 3 bedrooms, 2 T&B, dining\r\n  and living area, kitchen, service area, and parking. End unit for extra space.\r\n\r\n  Nearby: Gaisano Uptown, Rosevale School, Corpus Christi, Xavier Grade School, banks, and other establishments. Close\r\n  to the gate.\r\n\r\n  Available for long-term rent.";
        $listing->source_site    = 'rent_ph';
        $listing->source_url     = 'https://rent.ph/property/affordable-house-and-lot-for-rent-at-uptown-cdo';
        $listing->contact_phone  = '+639955103756';
        $listing->prequal_status = 'not_called';
        $listing->queue_status   = 'unassigned';
        $listing->is_verified    = false;
        $listing->is_featured    = false;
        $listing->save();

        $imageUrls = [
            'https://rentconnectph-dev-uploads.s3.ap-southeast-1.amazonaws.com/listings/019dda21-efbf-7204-a6e7-763d648802b5/a594f111-981b-4ce9-82b7-0d6939c0b897',
            'https://rentconnectph-dev-uploads.s3.ap-southeast-1.amazonaws.com/listings/019dda21-efbf-7204-a6e7-763d648802b5/83e83c28-1d67-43e3-9116-391e3c1e77e7',
        ];

        $firstImageId = null;
        foreach ($imageUrls as $i => $url) {
            $img = ListingImage::create([
                'listing_id' => $listing->id,
                'url'        => $url,
                'sort_order' => $i,
            ]);
            $firstImageId ??= $img->id;
        }

        if ($firstImageId !== null) {
            $listing->update(['display_image_id' => $firstImageId]);
        }

        // Per the source listing's description: only "Parking" maps cleanly to our
        // catalog (Semi-furnished is partial and intentionally omitted; gated
        // community isn't an amenity). Attach by slug so this stays portable
        // across catalog renames + sort_order shuffles.
        $amenitySlugs = ['parking'];
        $amenityIds = Amenity::whereIn('slug', $amenitySlugs)->pluck('id');
        $listing->amenities()->attach($amenityIds);

        $this->info("Created listing {$uuid} ({$listing->title}) with " . count($imageUrls) . " image(s) and " . $amenityIds->count() . " amenity(ies).");
        return self::SUCCESS;
    }
}
