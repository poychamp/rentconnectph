<?php

namespace App\Http\Controllers;

use App\Http\Resources\ListingDetailResource;
use App\Models\Listing;

class ListingController extends Controller
{
    public function show(Listing $listing)
    {
        $listing->load(['displayImage', 'images', 'amenities']);

        $payload = (new ListingDetailResource($listing))->resolve();

        return view('listings.show', ['listing' => $payload]);
    }
}
