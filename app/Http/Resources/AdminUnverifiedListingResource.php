<?php

namespace App\Http\Resources;

use App\Enums\Barangay;
use App\Enums\ListingType;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUnverifiedListingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'uuid'           => $this->uuid,
            'name'           => $this->title,
            'type_label'     => ListingType::from($this->type)->label,
            'barangay_label' => Barangay::from($this->barangay)->label,
            'price'          => $this->price_monthly,
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
