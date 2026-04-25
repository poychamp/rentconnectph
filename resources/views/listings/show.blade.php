@extends('layouts.app')

@section('title', $listing['title'] . ' — RentConnectPH')
@section('page', 'listing-detail')

@push('scripts')
<script>
    window.__INITIAL_LISTING__ = @json($listing);
</script>
@endpush
