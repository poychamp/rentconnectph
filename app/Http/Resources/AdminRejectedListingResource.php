<?php

namespace App\Http\Resources;

use App\Enums\Barangay;
use App\Enums\ListingType;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminRejectedListingResource extends JsonResource
{
    public function toArray($request): array
    {
        $event = $this->latestLifecycleEvent;

        return [
            'id'             => $this->id,
            'uuid'           => $this->uuid,
            'name'           => $this->title,
            'type_label'     => $this->type ? ListingType::from($this->type)->label : null,
            'barangay_label' => $this->barangay ? Barangay::from($this->barangay)->label : null,
            'price'          => $this->price_monthly,
            'created_at'     => $this->created_at?->toIso8601String(),
            'rejected_at'    => $this->deleted_at?->toIso8601String(),
            'rejected_by'    => $event?->actor?->name,
        ];
    }
}
