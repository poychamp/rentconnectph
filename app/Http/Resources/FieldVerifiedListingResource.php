<?php

namespace App\Http\Resources;

use App\Enums\Barangay;
use App\Enums\ListingType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FieldVerifiedListingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'              => $this->uuid,
            'title'             => $this->title,
            'type_label'        => $this->type ? ListingType::from($this->type)->label : null,
            'barangay_label'    => $this->barangay
                ? Barangay::from($this->barangay)->label
                : null,
            'price_monthly'     => $this->price_monthly,
            'directions'        => $this->directions,
            'display_image_url' => $this->displayImage?->url,
            'visited_at'        => $this->visited_at?->toIso8601String(),
            'verified_at'       => $this->verified_at?->toIso8601String(),
        ];
    }
}
