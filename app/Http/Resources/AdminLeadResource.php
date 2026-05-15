<?php

namespace App\Http\Resources;

use App\Enums\Barangay;
use App\Enums\ContactType;
use App\Enums\LeadStatus;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminLeadResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid'             => $this->uuid,
            'status'           => $this->status,
            'status_label'     => LeadStatus::from($this->status)->label,
            'created_at'       => $this->created_at?->toIso8601String(),
            'created_by_name'  => $this->createdBy?->name,
            'sent_at'          => $this->sent_at?->toIso8601String(),
            'finalized_at'     => $this->finalized_at?->toIso8601String(),
            'lost_at'          => $this->lost_at?->toIso8601String(),
            'notes'            => $this->notes,
            'monthly_rent'     => $this->inquiry->listing->price_monthly,
            'inquiry' => [
                'uuid'  => $this->inquiry->uuid,
                'notes' => $this->inquiry->notes,
            ],
            'renter' => [
                'name'         => $this->inquiry->renter->name,
                'phone'        => $this->inquiry->renter->phone,
                'is_qualified' => $this->inquiry->renter->is_qualified,
                'notes'        => $this->inquiry->renter->notes,
            ],
            'listing' => [
                'uuid'               => $this->inquiry->listing->uuid,
                'title'              => $this->inquiry->listing->title,
                'barangay_label'     => $this->inquiry->listing->barangay
                    ? Barangay::from($this->inquiry->listing->barangay)->label
                    : null,
                'contact_phone'      => $this->inquiry->listing->contact_phone,
                'contact_type_label' => $this->inquiry->listing->contact_type
                    ? ContactType::from($this->inquiry->listing->contact_type)->label
                    : null,
                'verification_notes' => $this->inquiry->listing->verification_notes,
            ],
            'listing_detail_url' => '/listings/' . $this->inquiry->listing->uuid,
        ];
    }
}
