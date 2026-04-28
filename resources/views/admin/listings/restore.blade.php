@extends('layouts.admin')

@section('title', 'Restore Listing — RentConnectPH Admin')
@section('page', 'admin-restore-listing')

@push('scripts')
@php
    $authed = auth('admin')->user();
    $initials = collect(explode(' ', $authed->name))
        ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
        ->take(2)
        ->implode('');

    $event = $listing->latestLifecycleEvent;

    $listingPayload = [
        'uuid'           => $listing->uuid,
        'title'          => $listing->title,
        'type_label'     => \App\Enums\ListingType::from($listing->type)->label,
        'barangay_label' => \App\Enums\Barangay::from($listing->barangay)->label,
        'price_monthly'  => $listing->price_monthly,
        'beds'           => $listing->beds,
        'baths'          => $listing->baths,
        'sqm'            => $listing->sqm,
        'description'    => $listing->description,
        'latitude'       => $listing->latitude,
        'longitude'      => $listing->longitude,
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

    $deactivationPayload = [
        'deactivated_at' => $listing->deleted_at?->toIso8601String(),
        'deactivated_by' => $event?->actor?->name,
        'reason_label'   => $event && $event->reason
            ? \App\Enums\DeactivationReason::from($event->reason)->label
            : null,
        'notes'          => $event?->notes,
    ];

    $mapboxStaticUrl = \App\Support\MapboxStaticUrl::forCoords($listing->latitude, $listing->longitude);
@endphp
<script>
    window.__INITIAL_DASHBOARD__ = {
        user: {
            name:       @json($authed->name),
            initials:   @json($initials),
            role_label: 'Super Admin',
        },
    };

    window.__INITIAL_RESTORE_LISTING__ = {
        listingUuid:     @json($listing->uuid),
        listing:         @json($listingPayload),
        photos:          @json($photosPayload),
        amenities:       @json($amenitiesPayload),
        deactivation:    @json($deactivationPayload),
        mapboxStaticUrl: @json($mapboxStaticUrl),

        restoreErrors: @json($errors->getBag('restore')->toArray() ?: null),
    };
</script>
@endpush
