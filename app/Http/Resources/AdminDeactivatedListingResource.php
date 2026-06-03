<?php

namespace App\Http\Resources;

use App\Enums\Barangay;
use App\Enums\DeactivationReason;
use App\Enums\ListingType;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminDeactivatedListingResource extends JsonResource
{
    public function toArray($request): array
    {
        $event = $this->latestLifecycleEvent;

        return [
            'id'                  => $this->id,
            'uuid'                => $this->uuid,
            'name'                => $this->title,
            'type_label'          => $this->type ? ListingType::from($this->type)->label : null,
            'barangay_label'      => $this->barangay ? Barangay::from($this->barangay)->label : null,
            'price'               => $this->price_monthly,
            'created_at'          => $this->created_at?->toIso8601String(),
            'deleted_at'          => $this->deleted_at?->toIso8601String(),
            'deactivation_reason' => $event?->reason
                ? DeactivationReason::from($event->reason)->label
                : null,
            'deactivated_by'      => $event?->actor?->name,
        ];
    }
}
