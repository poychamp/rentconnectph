@extends('layouts.admin')

@section('title', 'Edit Amenity — RentConnectPH Admin')
@section('page', 'admin-amenity-edit')

@push('scripts')
@php
    $authUser = (new \App\Http\Resources\AdminAuthUserResource(auth('admin')->user()))->resolve();
    $oldInput = [
        'name' => old('name', $amenity['name']),
        'slug' => old('slug', $amenity['slug']),
    ];
    $bagErrors = $errors->getBag('default')->toArray() ?: null;
@endphp
<script>
    window.__INITIAL_ADMIN_AMENITY_EDIT__ = {
        user:     @json($authUser),
        amenity:  @json($amenity),
        errors:   @json($bagErrors),
        oldInput: @json($oldInput),
    };
</script>
@endpush
