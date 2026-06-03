<?php

namespace App\Http\Resources;

use App\Enums\Barangay;
use App\Enums\ContactType;
use App\Enums\ListingType;
use App\Enums\SourceSite;
use App\Support\MapboxStaticUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FieldListingRequestVerificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $imagesCount = $this->images->count();

        return [
            'uuid'                => $this->uuid,
            'title'               => $this->title,
            'type'                => $this->type,
            'type_label'          => $this->type ? ListingType::from($this->type)->label : null,
            'barangay'            => $this->barangay,
            'barangay_label'      => $this->barangay ? Barangay::from($this->barangay)->label : null,
            'price_monthly'       => $this->price_monthly,
            'beds'                => $this->beds,
            'baths'               => $this->baths,
            'sqm'                 => $this->sqm,
            'latitude'            => $this->latitude,
            'longitude'           => $this->longitude,
            'description'         => $this->description,
            'directions'          => $this->directions,
            'verification_notes'  => $this->verification_notes,
            'listing_contact_id'  => $this->listing_contact_id,
            'listing_contact'     => $this->listingContact ? [
                'uuid'  => $this->listingContact->uuid,
                'phone' => $this->listingContact->phone,
                'name'  => $this->listingContact->name,
                'notes' => $this->listingContact->notes,
            ] : null,
            'contact_type'        => $this->contact_type,
            'contact_type_label'  => $this->contact_type ? ContactType::from($this->contact_type)->label : null,
            'source_site'         => $this->source_site,
            'source_site_label'   => $this->source_site ? SourceSite::from($this->source_site)->label : null,
            'source_url'          => $this->source_url,
            'images'              => $this->images->map(fn ($img) => [
                'id'  => $img->id,
                'url' => $img->url,
            ])->all(),
            'display_image_url'   => $this->displayImage?->url,
            'amenities'           => $this->amenities->map(fn ($a) => [
                'id'   => $a->id,
                'name' => $a->name,
            ])->all(),
            'mapbox_static_url'   => MapboxStaticUrl::forCoords($this->latitude, $this->longitude),
            'is_field_priority'   => (bool) $this->is_field_priority,
            'assigned_at'         => $this->assigned_at?->toIso8601String(),
            'created_at'          => $this->created_at?->toIso8601String(),
            'prereqs' => [
                'has_title'              => ! empty(trim((string) ($this->title ?? ''))),
                'has_directions'         => ! empty(trim((string) ($this->directions ?? ''))),
                'has_lat_lng'            => $this->latitude !== null && $this->longitude !== null,
                'has_at_least_one_photo' => $imagesCount >= 1,
            ],
        ];
    }
}
