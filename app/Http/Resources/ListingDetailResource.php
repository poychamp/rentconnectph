<?php

namespace App\Http\Resources;

use App\Enums\Barangay;
use App\Enums\ListingType;
use App\Support\MapboxStaticUrl;
use Illuminate\Http\Resources\Json\JsonResource;


class ListingDetailResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'title' => $this->title,
            'type' => $this->type,
            'type_label' => ListingType::from($this->type)->label,
            'price_monthly' => $this->price_monthly,
            'beds' => $this->beds,
            'baths' => $this->baths,
            'sqm' => $this->sqm,
            'barangay' => $this->barangay,
            'barangay_label' => Barangay::from($this->barangay)->label,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'description' => $this->description,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'listed_at' => $this->listed_at?->toIso8601String(),
            'images' => $this->images->map(fn ($img) => [
                'id' => $img->id,
                'uuid' => $img->uuid,
                'url' => $img->url,
                'sort_order' => $img->sort_order,
            ])->values(),
            'amenities' => AmenityResource::collection($this->amenities),
            'map_url' => MapboxStaticUrl::forCoords($this->latitude, $this->longitude),
        ];
    }
}
