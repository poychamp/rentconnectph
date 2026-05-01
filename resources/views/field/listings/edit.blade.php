@extends('layouts.field')

@section('title', 'Listing — RentConnectPH Field')
@section('page', 'field-listing-edit')

@push('scripts')
@php
    $authUser = (new \App\Http\Resources\AdminAuthUserResource(auth('admin')->user()))->resolve();

    // On validation failure, Laravel redirects back with `withInput`+`withErrors`.
    // Enrich submitted photos with renderable URLs so the photo grid stays
    // populated after the round-trip: existing_id → image url lookup, tmp key
    // → S3 GET url.
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
    window.__INITIAL_FIELD_LISTING_EDIT__ = {
        user:         @json($authUser),
        listing:      @json($listing),
        listingTypes: @json($listingTypes),
        barangays:    @json($barangays),
        sourceSites:  @json($sourceSites),
        contactTypes: @json($contactTypes),
        amenities:    @json($amenities),

        oldInput:     @json($oldData),
        errors:       @json($errors->getBag('default')->toArray() ?: null),
    };
</script>
@endpush
