<?php

namespace App\Http\Resources;

use App\Enums\Barangay;
use App\Enums\ListingType;
use Illuminate\Http\Resources\Json\JsonResource;

class HomeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'featured' => ListingCardResource::collection($this->resource['featured']),
            'recently' => ListingCardResource::collection($this->resource['recently']),
            'listingTypes' => collect(ListingType::toValues())
                ->map(fn ($v) => ['value' => $v, 'label' => ListingType::from($v)->label])
                ->values(),
            'barangays' => collect(Barangay::toValues())
                ->map(fn ($v) => ['value' => $v, 'label' => Barangay::from($v)->label])
                ->values(),
            'appStoreUrl' => config('services.app_store.ios_url'),
        ];
    }
}
