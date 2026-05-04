<?php

namespace App\Http\Resources;

use App\Enums\Barangay;
use App\Enums\ContactType;
use App\Enums\InquiryStatus;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminInquiryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid'               => $this->uuid,
            'status'             => $this->status,
            'status_label'       => InquiryStatus::from($this->status)->label,
            'submitted_at'       => $this->created_at?->toIso8601String(),
            'notes'              => $this->notes,
            'handed_off_at'      => $this->handed_off_at?->toIso8601String(),
            'rejected_at'        => $this->rejected_at?->toIso8601String(),
            'handed_off_by_name' => $this->handedOffBy?->name,
            'rejected_by_name'   => $this->rejectedBy?->name,
            'lead'               => $this->lead ? [
                'uuid'       => $this->lead->uuid,
                'created_at' => $this->lead->created_at?->toIso8601String(),
            ] : null,
            'renter' => [
                'name'         => $this->renter->name,
                'phone'        => $this->renter->phone,
                'is_qualified' => $this->renter->is_qualified,
                'notes'        => $this->renter->notes,
            ],
            'listing' => [
                'uuid'               => $this->listing->uuid,
                'title'              => $this->listing->title,
                'barangay_label'     => Barangay::from($this->listing->barangay)->label,
                'contact_phone'      => $this->listing->contact_phone,
                'contact_type_label' => $this->listing->contact_type
                    ? ContactType::from($this->listing->contact_type)->label
                    : null,
                'verification_notes' => $this->listing->verification_notes,
            ],
        ];
    }
}
