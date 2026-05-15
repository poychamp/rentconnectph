<?php

namespace App\Http\Resources;

use App\Enums\Barangay;
use App\Enums\ContactType;
use App\Enums\ListingType;
use App\Enums\PrequalStatus;
use App\Enums\QueueStatus;
use App\Enums\SourceSite;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FieldListingDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'                 => $this->uuid,
            'title'                => $this->title,
            'type'                 => $this->type,
            'type_label'           => $this->type ? ListingType::from($this->type)->label : null,
            'barangay'             => $this->barangay,
            'barangay_label'       => $this->barangay
                ? Barangay::from($this->barangay)->label
                : null,
            'price_monthly'        => $this->price_monthly,
            'beds'                 => $this->beds,
            'baths'                => $this->baths,
            'sqm'                  => $this->sqm,
            'latitude'             => $this->latitude,
            'longitude'            => $this->longitude,
            'description'          => $this->description,
            'directions'           => $this->directions,
            'verification_notes'   => $this->verification_notes,
            'contact_phone'        => $this->contact_phone,
            'contact_type'         => $this->contact_type,
            'contact_type_label'   => $this->contact_type
                ? ContactType::from($this->contact_type)->label
                : null,
            'source_site'          => $this->source_site,
            'source_site_label'    => $this->source_site
                ? SourceSite::from($this->source_site)->label
                : null,
            'source_url'           => $this->source_url,
            'prequal_status'       => $this->prequal_status,
            'prequal_status_label' => $this->prequal_status
                ? PrequalStatus::from($this->prequal_status)->label
                : null,
            'queue_status'         => $this->queue_status,
            'queue_status_label'   => $this->queue_status
                ? QueueStatus::from($this->queue_status)->label
                : null,
            'is_verified'          => (bool) $this->is_verified,
            'verified_at'          => $this->verified_at?->toIso8601String(),
            'visited_at'           => $this->visited_at?->toIso8601String(),
            'images'               => $this->images->map(fn ($img) => [
                'id'  => $img->id,
                'url' => $img->url,
            ])->all(),
            'display_image_url'    => $this->displayImage?->url,
            'amenities'            => $this->amenities->map(fn ($a) => [
                'id'   => $a->id,
                'name' => $a->name,
                'slug' => $a->slug,
                'icon' => $a->icon,
            ])->all(),
            'is_field_priority'    => (bool) $this->is_field_priority,
            'field_priority_order' => $this->field_priority_order,
            'assigned_at'          => $this->assigned_at?->toIso8601String(),
            'created_at'           => $this->created_at?->toIso8601String(),
        ];
    }
}
