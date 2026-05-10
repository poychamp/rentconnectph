<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ListingDetailResource;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;

class ListingController extends Controller
{
    public function show(Listing $listing): JsonResponse
    {
        abort_unless($listing->is_verified, 404);

        $listing->load(['images', 'amenities']);

        return response()->json([
            'listing' => (new ListingDetailResource($listing))->resolve(),
        ]);
    }
}
