<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Barangay;
use App\Enums\ListingType;
use App\Http\Controllers\Controller;
use App\Http\Resources\ListingCardResource;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;

class HomeController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $featured = Listing::with('displayImage')
            ->withCount('images')
            ->featured()
            ->verified()
            ->orderBy('featured_order')
            ->orderBy('id')
            ->get();

        $recent = Listing::with('displayImage')
            ->withCount('images')
            ->recentlyListed()
            ->limit(6)
            ->get();

        return response()->json([
            'featured' => $featured->map(fn ($l) => (new ListingCardResource($l))->resolve())->all(),
            'recently' => $recent->map(fn ($l) => (new ListingCardResource($l))->resolve())->all(),
            'catalogs' => [
                'listing_types' => $this->enumCatalog(ListingType::class),
                'barangays'     => $this->enumCatalog(Barangay::class),
            ],
        ]);
    }

    /**
     * @param  class-string<\Spatie\Enum\Enum>  $enum
     * @return array<int, array{value: string, label: string}>
     */
    private function enumCatalog(string $enum): array
    {
        return collect($enum::toValues())
            ->map(fn ($v) => ['value' => $v, 'label' => $enum::from($v)->label])
            ->all();
    }
}
