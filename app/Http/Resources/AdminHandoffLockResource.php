<?php

namespace App\Http\Resources;

use App\Enums\Barangay;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminHandoffLockResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'created_at'      => $this->created_at?->toIso8601String(),
            'created_by_name' => $this->createdBy?->name ?? 'Deleted admin',
            'listing' => [
                'uuid'           => $this->listing->uuid,
                'title'          => $this->listing->title,
                'barangay_label' => $this->listing->barangay
                    ? Barangay::from($this->listing->barangay)->label
                    : null,
                'contact_phone'  => $this->listing->contact_phone,
            ],
            'renter' => [
                'name'  => $this->inquiry->renter->name,
                'phone' => $this->inquiry->renter->phone,
            ],
        ];
    }
}
