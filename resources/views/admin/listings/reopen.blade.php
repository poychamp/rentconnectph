@extends('layouts.admin')

@section('title', 'Reopen Listing — RentConnectPH Admin')
@section('page', 'admin-reopen-listing')

@push('scripts')
@php
    $authUser = (new \App\Http\Resources\AdminAuthUserResource(auth('admin')->user()))->resolve();

    $event = $listing->latestLifecycleEvent;

    $listingPayload = [
        'uuid'               => $listing->uuid,
        'title'              => $listing->title,
        'type_label'         => \App\Enums\ListingType::from($listing->type)->label,
        'barangay_label'     => \App\Enums\Barangay::from($listing->barangay)->label,
        'price_monthly'      => $listing->price_monthly,
        'beds'               => $listing->beds,
        'baths'              => $listing->baths,
        'sqm'                => $listing->sqm,
        'description'        => $listing->description,
        'latitude'           => $listing->latitude,
        'longitude'          => $listing->longitude,
        'contact_phone'      => $listing->contact_phone,
        'contact_type_label' => $listing->contact_type ? \App\Enums\ContactType::from($listing->contact_type)->label : null,
        'source_site_label'  => $listing->source_site ? \App\Enums\SourceSite::from($listing->source_site)->label : null,
        'source_url'         => $listing->source_url,
        'directions'         => $listing->directions,
        'verification_notes' => $listing->verification_notes,
        'visited_at'         => $listing->visited_at?->toIso8601String(),
        'assigned_to_name'   => $listing->assignedTo?->name,
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

    // v1: reason and notes are always null on `rejected` events. The banner
    // renders these conditionally so a future RejectionReason PRD requires
    // no Vue changes — just populate the fields here.
    $rejectionPayload = [
        'rejected_at'  => $listing->deleted_at?->toIso8601String(),
        'rejected_by'  => $event?->actor?->name,
        'reason_label' => null,
        'notes'        => null,
    ];

    $mapboxStaticUrl = \App\Support\MapboxStaticUrl::forCoords($listing->latitude, $listing->longitude);
@endphp
<script>
    window.__INITIAL_DASHBOARD__ = {
        user: @json($authUser),
    };

    window.__INITIAL_REOPEN_LISTING__ = {
        listingUuid:     @json($listing->uuid),
        listing:         @json($listingPayload),
        photos:          @json($photosPayload),
        amenities:       @json($amenitiesPayload),
        rejection:       @json($rejectionPayload),
        mapboxStaticUrl: @json($mapboxStaticUrl),

        reopenErrors: @json($errors->getBag('reopen')->toArray() ?: null),
    };
</script>
@endpush
