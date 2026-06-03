<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ListingController extends Controller
{
    public function updateFeaturedOrder(Request $request): JsonResponse
    {
        $request->validate([
            'order'   => ['required', 'array', 'min:1'],
            'order.*' => ['required', 'string', 'uuid'],
        ]);

        $order = $request->input('order');

        $listings = Listing::featured()->verified()
            ->whereIn('uuid', $order)
            ->get()
            ->keyBy('uuid');

        if ($listings->count() !== count($order)) {
            throw ValidationException::withMessages([
                'order' => 'One or more listings are not in the featured-verified slice.',
            ]);
        }

        DB::transaction(function () use ($listings, $order) {
            foreach ($order as $i => $uuid) {
                $listings[$uuid]->update(['featured_order' => $i + 1]);
            }
        });

        return response()->json(['ok' => true]);
    }
}
