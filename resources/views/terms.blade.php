@extends('layouts.app')

@section('title', 'Terms of Service — RentConnectPH')
@section('description', 'Terms of Service for RentConnectPH — the rules governing use of our rental-listings platform for Cagayan de Oro.')
@section('og_title', 'Terms of Service — RentConnectPH')
@section('og_description', 'Terms of Service for RentConnectPH — the rules governing use of our rental-listings platform for Cagayan de Oro.')
@section('og_image', asset('og/brand.png'))
@section('page', 'terms')

@push('scripts')
<script>
    window.__INITIAL_TERMS__ = @json([
        'effectiveDate' => '2026-05-14',
    ]);
</script>
@endpush
