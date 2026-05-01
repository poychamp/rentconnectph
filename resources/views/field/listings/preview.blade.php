@extends('layouts.field')

@section('title', 'Preview Listing — RentConnectPH Field')
@section('page', 'field-listing-preview')

@push('scripts')
@php
    $authUser = (new \App\Http\Resources\AdminAuthUserResource(auth('admin')->user()))->resolve();

    $listingPayload = [
        'uuid'                => $listing->uuid,
        'title'               => $listing->title,
        'type_label'          => $listing->type ? \App\Enums\ListingType::from($listing->type)->label : null,
        'barangay_label'      => $listing->barangay ? \App\Enums\Barangay::from($listing->barangay)->label : null,
        'price_monthly'       => $listing->price_monthly,
        'beds'                => $listing->beds,
        'baths'               => $listing->baths,
        'sqm'                 => $listing->sqm,
        'description'         => $listing->description,
        'directions'          => $listing->directions,
        'latitude'            => $listing->latitude,
        'longitude'           => $listing->longitude,
        'contact_phone'       => $listing->contact_phone,
        'contact_type_label'  => $listing->contact_type ? \App\Enums\ContactType::from($listing->contact_type)->label : null,
        'source_site_label'   => $listing->source_site ? \App\Enums\SourceSite::from($listing->source_site)->label : null,
        'source_url'          => $listing->source_url,
        'verification_notes'  => $listing->verification_notes,
        'visited_at'          => $listing->visited_at?->toIso8601String(),
        'is_verified'         => (bool) $listing->is_verified,
        'verified_at'         => $listing->verified_at?->toIso8601String(),
    ];

    $photosPayload = $listing->images->map(fn ($img) => [
        'id'         => $img->id,
        'url'        => $img->url,
        'sort_order' => $img->sort_order,
    ])->values()->all();

    $amenitiesPayload = $listing->amenities->map(fn ($a) => [
        'id'   => $a->id,
        'name' => $a->name,
        'slug' => $a->slug,
        'icon' => $a->icon,
    ])->values()->all();

    $mapboxStaticUrl = \App\Support\MapboxStaticUrl::forCoords($listing->latitude, $listing->longitude);
@endphp
<script>
    window.__INITIAL_FIELD_LISTING_PREVIEW__ = {
        user:            @json($authUser),
        listing:         @json($listingPayload),
        photos:          @json($photosPayload),
        amenities:       @json($amenitiesPayload),
        mapboxStaticUrl: @json($mapboxStaticUrl),
        from:            @json($from),
    };
</script>
@endpush
