@extends('layouts.app')

@section('title', 'Inquiry submitted — RentConnectPH')
@section('page', 'inquiry-success')

@push('scripts')
<script>
    window.__INITIAL_INQUIRY_SUCCESS__ = {
        listing: @json($listing ?? null),
    };
</script>
@endpush
