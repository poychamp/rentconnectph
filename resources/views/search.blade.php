@extends('layouts.app')

@section('title', 'Search Listings — RentConnectPH')
@section('description', 'Search rental listings in Cagayan de Oro by price, location, beds, and amenities. Filter verified properties from local property owners.')
@section('page', 'search')

@push('scripts')
<script>
    window.__INITIAL_SEARCH__ = @json($search);
</script>
@endpush
