<?php

namespace App\Http\Resources;

use App\Enums\Barangay;
use App\Enums\ContactType;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminInquiryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid'         => $this->uuid,
            'submitted_at' => $this->created_at?->toIso8601String(),
            'notes'        => $this->notes,
            'renter' => [
                'name'         => $this->renter->name,
                'phone'        => $this->renter->phone,
                'is_qualified' => $this->renter->is_qualified,
                'notes'        => $this->renter->notes,
            ],
            'listing' => [
                'uuid'               => $this->listing->uuid,
                'title'              => $this->listing->title,
                'barangay_label'     => $this->listing->barangay
                    ? Barangay::from($this->listing->barangay)->label
                    : null,
                'listing_contact'    => $this->listing->listingContact ? [
                    'uuid'  => $this->listing->listingContact->uuid,
                    'phone' => $this->listing->listingContact->phone,
                    'name'  => $this->listing->listingContact->name,
                    'notes' => $this->listing->listingContact->notes,
                ] : null,
                'contact_type_label' => $this->listing->contact_type
                    ? ContactType::from($this->listing->contact_type)->label
                    : null,
                'verification_notes' => $this->listing->verification_notes,
            ],
        ];
    }
}
