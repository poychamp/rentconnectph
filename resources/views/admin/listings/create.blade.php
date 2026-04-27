@extends('layouts.admin')

@section('title', 'Add Listing — RentConnectPH')
@section('page', 'add-listing')

@push('scripts')
@php
    $authed = auth('admin')->user();
    $initials = collect(explode(' ', $authed->name))
        ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
        ->take(2)
        ->implode('');

    // Enrich old('photos') with the S3 GET URL for each tmp key so PhotoUpload
    // can show the preview after a redirect-back. The blob: preview URL we used
    // before submit doesn't survive the page reload.
    $oldData = old() ?: null;
    if ($oldData && ! empty($oldData['photos']) && is_array($oldData['photos'])) {
        $oldData['photos'] = collect($oldData['photos'])
            ->map(function ($photo) {
                if (is_array($photo) && ! empty($photo['key'])) {
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

    window.__INITIAL_ADD_LISTING__ = {
        listingTypes: @json($listingTypes),
        barangays:    @json($barangays),
        amenities:    @json($amenities),

        // Populated by Laravel's redirect()->back()->withErrors()->withInput()
        // when validation fails. Both null on a fresh page load.
        oldInput: @json($oldData),
        errors:   @json($errors->toArray() ?: null),
    };
</script>
@endpush
