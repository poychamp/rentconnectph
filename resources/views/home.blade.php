@extends('layouts.app')

@section('title', 'RentConnectPH — Find your next home in CDO')
@section('description', 'Browse verified rental listings in Cagayan de Oro. Apartments, condos, houses, and bedspaces from owner-direct properties with transparent pricing.')
@section('page', 'home')

@push('scripts')
<script>
    window.__INITIAL_HOME__ = @json($home);
</script>
@endpush
