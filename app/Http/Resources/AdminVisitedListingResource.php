<?php

namespace App\Http\Resources;

use App\Enums\Barangay;
use App\Enums\ListingType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminVisitedListingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'uuid'             => $this->uuid,
            'name'             => $this->title,
            'type_label'       => $this->type ? ListingType::from($this->type)->label : null,
            'barangay_label'   => $this->barangay
                ? Barangay::from($this->barangay)->label
                : null,
            'price_monthly'    => $this->price_monthly,
            'directions'       => $this->directions,
            'contact_phone'    => $this->listingContact?->phone,
            'assigned_to_name' => $this->assignedTo?->name,
            'visited_at'       => $this->visited_at?->toIso8601String(),
            'updated_at'       => $this->updated_at->toIso8601String(),
        ];
    }
}
