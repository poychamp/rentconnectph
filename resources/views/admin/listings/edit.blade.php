@extends('layouts.admin')

@section('title', 'Admin Edit Listing — RentConnectPH')
@section('page', 'admin-edit-listing')

@push('scripts')
@php
    $authed = auth('admin')->user();
    $initials = collect(explode(' ', $authed->name))
        ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
        ->take(2)
        ->implode('');

    $listingFormShape = [
        'title'         => $listing->title,
        'description'   => $listing->description,
        'listing_type'  => $listing->type,
        'monthly_rent'  => $listing->price_monthly,
        'barangay'      => $listing->barangay,
        'beds'          => $listing->beds,
        'baths'         => $listing->baths,
        'sqm'           => $listing->sqm,
        'latitude'      => $listing->latitude,
        'longitude'     => $listing->longitude,
        'amenities'     => $listing->amenities->pluck('id')->all(),
        'photos'        => $listing->images->map(fn ($img) => [
            'existing_id' => $img->id,
            'url'         => $img->url,
            'sort_order'  => $img->sort_order,
        ])->values()->all(),
        'is_verified'   => (bool) $listing->is_verified,
        'is_featured'   => (bool) $listing->is_featured,
    ];

    $oldData = old() ?: null;
    if ($oldData && ! empty($oldData['photos']) && is_array($oldData['photos'])) {
        $existingUrlById = $listing->images->pluck('url', 'id')->all();

        $oldData['photos'] = collect($oldData['photos'])
            ->map(function ($photo) use ($existingUrlById) {
                if (! is_array($photo)) return $photo;

                if (! empty($photo['existing_id']) && isset($existingUrlById[$photo['existing_id']])) {
                    $photo['url'] = $existingUrlById[$photo['existing_id']];
                } elseif (! empty($photo['key'])) {
                    $photo['url'] = \Illuminate\Support\Facades\Storage::disk('s3')->url($photo['key']);
                }
                return $photo;
            })
            ->all();
    }
@endphp
<script>
    window.__INITIAL_DASHBOARD__ = {
        user: {
            name:       @json($authed->name),
            initials:   @json($initials),
            role_label: 'Super Admin',
        },
    };

    window.__INITIAL_EDIT_LISTING__ = {
        listingUuid:         @json($listing->uuid),
        listing:             @json($listingFormShape),
        listingTypes:        @json($listingTypes),
        barangays:           @json($barangays),
        amenities:           @json($amenities),
        deactivationReasons: @json($deactivationReasons),

        oldInput:         @json($oldData),
        errors:           @json($errors->getBag('default')->toArray() ?: null),
        deactivateErrors: @json($errors->getBag('deactivate')->toArray() ?: null),
        rejectErrors:     @json($errors->getBag('reject')->toArray() ?: null),
    };
</script>
@endpush
