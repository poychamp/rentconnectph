@extends('layouts.app')

@section('title', 'About — RentConnectPH')
@section('description', 'RentConnectPH is Cagayan de Oro\'s owner-direct rental platform. Every verified listing is checked in person by our team before it goes live.')
@section('og_title', 'About RentConnectPH')
@section('og_description', 'RentConnectPH is Cagayan de Oro\'s owner-direct rental platform. Every verified listing is checked in person by our team before it goes live.')
@section('og_image', asset('og/about.png'))
@section('page', 'about')

@push('scripts')
<script>
    window.__INITIAL_ABOUT__ = @json([
        'brokerImage' => asset('img/broker.webp'),
    ]);
</script>
@endpush
