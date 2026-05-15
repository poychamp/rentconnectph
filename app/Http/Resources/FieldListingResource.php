<?php

namespace App\Http\Resources;

use App\Enums\Barangay;
use App\Enums\ContactType;
use App\Enums\ListingType;
use App\Enums\SourceSite;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FieldListingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'                 => $this->uuid,
            'title'                => $this->title,
            'type_label'           => $this->type ? ListingType::from($this->type)->label : null,
            'barangay_label'       => $this->barangay
                ? Barangay::from($this->barangay)->label
                : null,
            'price_monthly'        => $this->price_monthly,
            'beds'                 => $this->beds,
            'baths'                => $this->baths,
            'sqm'                  => $this->sqm,
            'directions'           => $this->directions,
            'contact_phone'        => $this->contact_phone,
            'contact_type_label'   => $this->contact_type
                ? ContactType::from($this->contact_type)->label
                : null,
            'source_site_label'    => $this->source_site
                ? SourceSite::from($this->source_site)->label
                : null,
            'source_url'           => $this->source_url,
            'display_image_url'    => $this->displayImage?->url,
            'assigned_at'          => $this->assigned_at?->toIso8601String(),
            'field_priority_order' => $this->field_priority_order,
            'is_field_priority'    => (bool) $this->is_field_priority,
        ];
    }
}
