<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f97316">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <title>@yield('title', 'RentConnectPH Field')</title>

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

    @vite(['resources/css/admin.css', 'resources/js/admin.js'])

    @php
        // Sidebar action-needed counters. Permission-gated so unauthorized
        // pages (e.g. /auth/login during field-side bounces) don't run the
        // COUNT query. Mirrors Listing::scopeForOfficer used by the index
        // controller, so badge total = page row count for this officer.
        $fieldUser = auth('admin')->user();
        $fieldSidebarBadges = [];

        if ($fieldUser?->can(\App\Enums\AppPermission::listingsFieldWork()->value)) {
            $fieldSidebarBadges['queuedListings'] = \App\Models\Listing::forOfficer($fieldUser->id)->count();
        }
    @endphp

    <script>
        window.__ASSETS__ = {
            mapboxToken: "{{ config('services.mapbox.token') }}",
        };

        // Flash messages — picked up by AdminToast (mounted in admin.js on every page).
        // Toast infrastructure is surface-agnostic; same envelope shape as the admin layout.
        window.__FLASH__ = {
            success: @json(session('success')),
            error:   @json(session('error')),
            info:    @json(session('info')),
        };

        // Sidebar action-needed counters. FieldSidebar.vue reads this and
        // renders a badge next to items whose `badge: '<key>'` matches.
        window.__FIELD_SIDEBAR__ = {
            badges: @json($fieldSidebarBadges),
        };
    </script>

    @stack('scripts')
</head>
<body class="font-sans antialiased bg-gray-50 dark:bg-gray-950">
    <div id="app" data-page="@yield('page')"></div>
    <div id="admin-toast"></div>
</body>
</html>
