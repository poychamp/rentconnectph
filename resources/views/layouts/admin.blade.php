<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'RentConnectPH Admin')</title>

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
        // Sidebar action-needed counters. Each block is permission-gated so
        // unauthorized pages (e.g. /auth/login) don't run COUNT queries.
        // Each query mirrors its index controller exactly so badge totals
        // match what the admin sees on click-through.
        $adminUser = auth('admin')->user();
        $adminSidebarBadges = [];

        if ($adminUser?->can('listings.manage')) {
            $adminSidebarBadges['unverifiedListings'] = \App\Models\Listing::where('is_verified', false)
                ->whereIn('queue_status', ['unassigned', 'assigned'])
                ->count();
            $adminSidebarBadges['visitedListings'] = \App\Models\Listing::awaitingVerification()->count();
        }

        if ($adminUser?->can('inquiries.manage')) {
            $adminSidebarBadges['filteredInquiries'] = \App\Models\Inquiry::query()
                ->where('status', \App\Enums\InquiryStatus::new()->value)
                ->whereHas('listing', fn ($q) => $q->whereDoesntHave('activeHandoff'))
                ->count();
            $adminSidebarBadges['handoffs'] = \App\Models\HandoffLock::count();
            // "New leads" = pending only — admin hasn't sent to broker yet.
            $adminSidebarBadges['pendingLeads'] = \App\Models\Lead::where('status', \App\Enums\LeadStatus::pending()->value)
                ->count();
        }
    @endphp

    <script>
        window.__ASSETS__ = {
            mapboxToken: "{{ config('services.mapbox.token') }}",
        };

        // Flash messages — picked up by AdminToast (mounted in admin.js on every page).
        // Reads session('success') / session('error') / session('info') if any are set.
        window.__FLASH__ = {
            success: @json(session('success')),
            error:   @json(session('error')),
            info:    @json(session('info')),
        };

        // Sidebar action-needed counters. AdminSidebar.vue reads this and
        // renders a badge next to items whose `badge: '<key>'` matches.
        window.__ADMIN_SIDEBAR__ = {
            badges: @json($adminSidebarBadges),
        };
    </script>

    @stack('scripts')
</head>
<body class="font-sans antialiased bg-gray-50 dark:bg-gray-950">
    <div id="app" data-page="@yield('page')"></div>
    <div id="admin-toast"></div>
</body>
</html>
