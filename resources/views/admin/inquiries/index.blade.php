@extends('layouts.admin')

@section('title', 'All Inquiries — RentConnectPH Admin')
@section('page', 'admin-inquiries')

@push('scripts')
@php
    $authUser = (new \App\Http\Resources\AdminAuthUserResource(auth('admin')->user()))->resolve();
@endphp
<script>
    window.__INITIAL_INQUIRIES__ = {
        user:      @json($authUser),
        inquiries: @json($inquiries),
        counts:    @json($counts),
        filters:   @json($filters),
    };
</script>
@endpush
