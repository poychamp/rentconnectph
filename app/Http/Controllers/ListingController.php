<?php

namespace App\Http\Controllers;

use App\Http\Resources\ListingDetailResource;
use App\Models\Listing;

class ListingController extends Controller
{
    public function show(Listing $listing)
    {
        abort_unless($listing->is_verified, 404);

        $listing->load(['displayImage', 'images', 'amenities']);

        $payload = (new ListingDetailResource($listing))->resolve();

        return view('listings.show', ['listing' => $payload]);
    }
}
