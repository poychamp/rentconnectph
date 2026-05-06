<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('description', 'RentConnectPH connects renters with verified property owners across Cagayan de Oro. Browse apartments, condos, houses, and bedspaces with transparent pricing.')">

    <meta property="og:site_name" content="RentConnectPH">
    <meta property="og:locale" content="en_PH">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:url" content="@yield('og_url', url()->current())">
    <meta property="og:title" content="@yield('og_title', 'RentConnectPH — Verified rentals in Cagayan de Oro')">
    <meta property="og:description" content="@yield('og_description', 'Browse verified rental listings in Cagayan de Oro. Apartments, condos, houses, and bedspaces from owner-direct properties with transparent pricing.')">
    <meta property="og:image" content="@yield('og_image', asset('og/brand.webp'))">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">

    <meta name="theme-color" content="#f97316">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="manifest" href="/manifest.json">
    <title>@yield('title', 'RentConnectPH')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <script>
        (function () {
            var saved = localStorage.getItem('theme');
            var dark = saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches);
            if (dark) document.documentElement.classList.add('dark');
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script>
        window.__ASSETS__ = {
            hero: "{{ asset('img/cdo-hero.webp') }}",
            mapboxToken: "{{ config('services.mapbox.token') }}",
        };
    </script>

    @stack('scripts')
</head>
<body class="font-sans antialiased">
    <div id="app" data-page="@yield('page', 'home')"></div>
</body>
</html>
