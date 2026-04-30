<?php

namespace App\Http\Resources;

use App\Enums\Barangay;
use App\Enums\ListingType;
use App\Enums\PrequalStatus;
use App\Enums\QueueStatus;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUnverifiedListingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'uuid'                 => $this->uuid,
            'name'                 => $this->title,
            'type_label'           => ListingType::from($this->type)->label,
            'barangay_label'       => Barangay::from($this->barangay)->label,
            'contact_phone'        => $this->contact_phone,
            'prequal_status'       => $this->prequal_status,
            'prequal_status_label' => $this->prequal_status
                ? PrequalStatus::from($this->prequal_status)->label
                : null,
            'queue_status'         => $this->queue_status,
            'queue_status_label'   => $this->queue_status
                ? QueueStatus::from($this->queue_status)->label
                : null,
            'assigned_to_name'     => $this->assignedTo?->name,
            'created_at'           => $this->created_at?->toIso8601String(),
            'updated_at'           => $this->updated_at?->toIso8601String(),
        ];
    }
}
