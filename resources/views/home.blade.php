@extends('layouts.app')

@section('title', 'RentConnectPH — Find your next home in CDO')
@section('page', 'home')

@push('scripts')
<script>
    window.__INITIAL_HOME__ = @json($home);
</script>
@endpush
