@extends('layouts.app')

@section('title', 'Search Listings — RentConnectPH')
@section('page', 'search')

@push('scripts')
<script>
    window.__INITIAL_SEARCH__ = @json($search);
</script>
@endpush
