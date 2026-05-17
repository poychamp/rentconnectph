@extends('layouts.admin')

@section('title', 'Admin Edit Listing — RentConnectPH')
@section('page', 'admin-unverified-edit-listing')

@push('scripts')
@php
    $authUser = (new \App\Http\Resources\AdminAuthUserResource(auth('admin')->user()))->resolve();

    // Unverified slice — `$listing` is an associative array (not a Model)
    // shaped by Admin\ListingController::unverifiedEdit(). Array access only.
    $listingFormShape = [
        'title'         => $listing['title'],
        'description'   => $listing['description'],
        'listing_type'  => $listing['type'],
        'price_monthly' => $listing['price_monthly'],
        'barangay'      => $listing['barangay'],
        'beds'          => $listing['beds'],
        'baths'         => $listing['baths'],
        'sqm'           => $listing['sqm'],
        'latitude'      => $listing['latitude'],
        'longitude'     => $listing['longitude'],
        'source_site'        => $listing['source_site'],
        'source_url'         => $listing['source_url'],
        'contact'            => [
            'uuid'  => $listing['listing_contact']['uuid'],
            'phone' => $listing['listing_contact']['phone'],
            'name'  => $listing['listing_contact']['name'],
            'notes' => $listing['listing_contact']['notes'],
        ],
        'prequal_status'     => $listing['prequal_status'],
        'queue_status'       => $listing['queue_status'],
        'directions'         => $listing['directions'],
        'contact_type'       => $listing['contact_type'],
        'verification_notes' => $listing['verification_notes'],
        'assigned_to'        => $listing['assigned_to'],
        'amenities'          => collect($listing['amenities'])->pluck('id')->all(),
        'photos'        => collect($listing['images'])->map(fn ($img) => [
            'existing_id' => $img['id'],
            'url'         => $img['url'],
            'sort_order'  => $img['sort_order'],
        ])->values()->all(),
    ];

    $oldData = old() ?: null;
    if ($oldData && ! empty($oldData['photos']) && is_array($oldData['photos'])) {
        $existingUrlById = collect($listing['images'])
            ->mapWithKeys(fn ($img) => [$img['id'] => $img['url']])
            ->all();

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
        listingTypes:        @json($listingTypes),
        barangays:           @json($barangays),
        sourceSites:         @json($sourceSites),
        amenities:           @json($amenities),
        deactivationReasons: @json($deactivationReasons),
        fieldUsers:          @json($fieldUsers),
        contactTypes:        @json($contactTypes),

        oldInput:         @json($oldData),
        errors:           @json($errors->getBag('default')->toArray() ?: null),
        deactivateErrors: @json($errors->getBag('deactivate')->toArray() ?: null),
        rejectErrors:     @json($errors->getBag('reject')->toArray() ?: null),
    };
</script>
@endpush
