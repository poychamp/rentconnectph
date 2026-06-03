@extends('layouts.admin')

@section('title', 'Verify Listing — RentConnectPH Admin')
@section('page', 'admin-verify-edit-listing')

@push('scripts')
@php
    $authUser = (new \App\Http\Resources\AdminAuthUserResource(auth('admin')->user()))->resolve();

    // Enrich photos in oldInput with S3 URLs so previews survive a 422 round-trip.
    // Mixed-shape: existing-id entries get their URL via lookup map, tmp/ entries
    // get a fresh signed/url() from the S3 disk. Per CLAUDE.md "Server-side
    // enrichment of `old()`".
    $existingPhotosById = collect($listing['images'] ?? [])->keyBy('id');
    $oldInput = old() ?: null;
    if ($oldInput && isset($oldInput['photos']) && is_array($oldInput['photos'])) {
        $oldInput['photos'] = collect($oldInput['photos'])->map(function ($p) use ($existingPhotosById) {
            if (isset($p['existing_id']) && $existingPhotosById->has((int) $p['existing_id'])) {
                $p['url'] = $existingPhotosById[(int) $p['existing_id']]['url'];
                $p['sort_order'] = $existingPhotosById[(int) $p['existing_id']]['sort_order'] ?? null;
            } elseif (isset($p['key']) && $p['key']) {
                $p['url'] = \Illuminate\Support\Facades\Storage::disk('s3')->url($p['key']);
            }
            return $p;
        })->all();
    }
@endphp
<script>
    window.__INITIAL_VERIFY_EDIT_LISTING__ = {
        user:         @json($authUser),
        listing:      @json($listing),
        listingTypes: @json($listingTypes),
        barangays:    @json($barangays),
        sourceSites:  @json($sourceSites),
        contactTypes: @json($contactTypes),
        amenities:    @json($amenities),
        oldInput:     @json($oldInput),
        errors:       @json($errors->getBag('default')->toArray() ?: null),
        verifyErrors: @json($errors->getBag('verify')->toArray() ?: null),
        rejectErrors: @json($errors->getBag('reject')->toArray() ?: null),
    };
</script>
@endpush
