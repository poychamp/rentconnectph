@extends('layouts.app')

@section('title', 'Inquiry submitted — RentConnectPH')
@section('page', 'inquiry-success')

@push('scripts')
<script>
    window.__INITIAL_SUCCESS__ = {
        listingTitle: @json($listingTitle ?? null),
    };
</script>
@endpush
