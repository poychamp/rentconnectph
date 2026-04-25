<?php

namespace App\Http\Resources;

use App\Enums\Barangay;
use App\Enums\ListingType;
use Illuminate\Http\Resources\Json\JsonResource;

class ListingCardResource extends JsonResource
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
            'sqft' => $this->sqft,
            'barangay' => $this->barangay,
            'barangay_label' => Barangay::from($this->barangay)->label,
            'image' => $this->displayImage?->url,
            'section' => $this->is_featured ? 'verified' : 'recently',
        ];
    }
}
