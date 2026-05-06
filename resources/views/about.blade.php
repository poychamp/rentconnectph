@extends('layouts.app')

@section('title', 'About — RentConnectPH')
@section('description', 'RentConnectPH is Cagayan de Oro\'s owner-direct rental platform. Verified listings from local property owners with PRC-licensed broker support at closing.')
@section('og_title', 'About RentConnectPH')
@section('og_description', 'RentConnectPH is Cagayan de Oro\'s owner-direct rental platform. Verified listings from local property owners with PRC-licensed broker support at closing.')
@section('og_image', asset('og/about.webp'))
@section('page', 'about')

@push('scripts')
<script>
    window.__INITIAL_ABOUT__ = @json([
        'brokerImage' => asset('img/broker.webp'),
    ]);
</script>
@endpush
