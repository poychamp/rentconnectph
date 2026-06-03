@extends('layouts.field')

@section('title', 'Request Verification — RentConnectPH Field')
@section('page', 'field-request-verification')

@push('scripts')
@php
    $authUser = (new \App\Http\Resources\AdminAuthUserResource(auth('admin')->user()))->resolve();

    // Redirect-back enrichment: existing_id photos get URLs from the listing's
    // image array; tmp/ key photos get S3 GET URLs. Without this, photo
    // thumbnails go blank after a failed submit because the submitted payload
    // only carries the discriminator (existing_id) or the key.
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
    window.__INITIAL_FIELD_REQUEST_VERIFICATION__ = {
        user:         @json($authUser),
        listing:      @json($listing),
        listingTypes: @json($listingTypes),
        barangays:    @json($barangays),
        sourceSites:  @json($sourceSites),
        contactTypes: @json($contactTypes),
        amenities:    @json($amenities),

        oldInput:     @json($oldData),
        errors:       @json($errors->getBag('requestVerification')->toArray() ?: null),
    };
</script>
@endpush
