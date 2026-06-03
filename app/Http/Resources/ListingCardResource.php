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
            'type_label' => $this->type ? ListingType::from($this->type)->label : null,
            'price_monthly' => $this->price_monthly,
            'beds' => $this->beds,
            'baths' => $this->baths,
            'sqm' => $this->sqm,
            'barangay' => $this->barangay,
            'barangay_label' => $this->barangay ? Barangay::from($this->barangay)->label : null,
            'image' => $this->displayImage?->url,
            'image_count' => $this->images_count ?? 0,
            'section' => $this->is_featured ? 'verified' : 'recently',
        ];
    }
}
