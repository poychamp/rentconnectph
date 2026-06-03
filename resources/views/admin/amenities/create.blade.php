@extends('layouts.admin')

@section('title', 'Add Amenity — RentConnectPH Admin')
@section('page', 'admin-amenity-create')

@push('scripts')
@php
    $authUser = (new \App\Http\Resources\AdminAuthUserResource(auth('admin')->user()))->resolve();
    $oldInput = [
        'name' => old('name', ''),
        'slug' => old('slug', ''),
    ];
    $bagErrors = $errors->getBag('default')->toArray() ?: null;
@endphp
<script>
    window.__INITIAL_ADMIN_AMENITY_CREATE__ = {
        user:     @json($authUser),
        errors:   @json($bagErrors),
        oldInput: @json($oldInput),
    };
</script>
@endpush
