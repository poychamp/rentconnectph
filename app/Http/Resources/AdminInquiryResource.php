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
            'uuid'         => $this->uuid,
            'status'       => $this->status,
            'status_label' => InquiryStatus::from($this->status)->label,
            'submitted_at' => $this->created_at?->toIso8601String(),
            'renter' => [
                'name'                 => $this->renter->name,
                'phone'                => $this->renter->phone,
                'is_qualified'         => (bool) $this->renter->qualified,
                'prior_rejected_count' => (int) ($this->renter->dead_inquiries_count ?? 0),
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
