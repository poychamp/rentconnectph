<?php

namespace App\Http\Resources;

use App\Enums\Barangay;
use App\Enums\ListingType;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminVerifiedListingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'uuid'           => $this->uuid,
            'name'           => $this->title,
            'type_label'     => $this->type ? ListingType::from($this->type)->label : null,
            'barangay_label' => $this->barangay ? Barangay::from($this->barangay)->label : null,
            'price'          => $this->price_monthly,
            'verified_at'    => $this->verified_at?->toIso8601String(),
            'listed_at'      => $this->listed_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
