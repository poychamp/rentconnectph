@extends('layouts.admin')

@section('title', 'Admin Edit Listing — RentConnectPH')
@section('page', 'admin-edit-listing')

@push('scripts')
@php
    $authUser = (new \App\Http\Resources\AdminAuthUserResource(auth('admin')->user()))->resolve();

    $listingFormShape = [
        'title'              => $listing['title'],
        'description'        => $listing['description'],
        'listing_type'       => $listing['type'],
        'price_monthly'      => $listing['price_monthly'],
        'barangay'           => $listing['barangay'],
        'beds'               => $listing['beds'],
        'baths'              => $listing['baths'],
        'sqm'                => $listing['sqm'],
        'latitude'           => $listing['latitude'],
        'longitude'          => $listing['longitude'],
        'source_site'        => $listing['source_site'],
        'source_url'         => $listing['source_url'],
        'contact_phone'      => $listing['contact_phone'],
        'directions'         => $listing['directions'],
        'contact_type'       => $listing['contact_type'],
        'verification_notes' => $listing['verification_notes'],
        'amenities'          => collect($listing['amenities'])->pluck('id')->all(),
        'photos'             => collect($listing['images'])->map(fn ($img) => [
            'existing_id' => $img['id'],
            'url'         => $img['url'],
            'sort_order'  => $img['sort_order'],
        ])->values()->all(),
        'is_verified'        => true,
        'is_featured'        => $listing['is_featured'],
    ];

    $oldData = old() ?: null;
    if ($oldData && ! empty($oldData['photos']) && is_array($oldData['photos'])) {
        $existingUrlById = collect($listing['images'])->pluck('url', 'id')->all();

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
        user: @json($authUser),
    };

    window.__INITIAL_EDIT_LISTING__ = {
        listingUuid:         @json($listing['uuid']),
        listing:             @json($listingFormShape),
        assignedToName:      @json($listing['assigned_to_name']),
        visitedAt:           @json($listing['visited_at']),
        listingTypes:        @json($listingTypes),
        barangays:           @json($barangays),
        sourceSites:         @json($sourceSites),
        contactTypes:        @json($contactTypes),
        amenities:           @json($amenities),
        deactivationReasons: @json($deactivationReasons),

        oldInput:         @json($oldData),
        errors:           @json($errors->getBag('default')->toArray() ?: null),
        deactivateErrors: @json($errors->getBag('deactivate')->toArray() ?: null),
        rejectErrors:     @json($errors->getBag('reject')->toArray() ?: null),
    };
</script>
@endpush
