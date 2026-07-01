<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('description', 'RentConnectPH connects renters with verified property owners across Cagayan de Oro. Browse apartments, condos, houses, and bedspaces with transparent pricing.')">

    <link rel="canonical" href="{{ rtrim(config('app.url'), '/') . request()->getRequestUri() }}">

    <meta name="apple-itunes-app" content="app-id={{ config('services.app_store.ios_app_store_id') }}, app-argument={{ rtrim(config('app.url'), '/') . request()->getRequestUri() }}">

    <meta property="og:site_name" content="RentConnectPH">
    <meta property="og:locale" content="en_PH">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:url" content="@yield('og_url', url()->current())">
    <meta property="og:title" content="@yield('og_title', 'RentConnectPH — Verified rentals in Cagayan de Oro')">
    <meta property="og:description" content="@yield('og_description', 'Browse verified rental listings in Cagayan de Oro. Apartments, condos, houses, and bedspaces from owner-direct properties with transparent pricing.')">
    <meta property="og:image" content="@yield('og_image', asset('og/brand.png'))">
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
            googleMapsEnabled: {{ config('services.google_maps.api_key') ? 'true' : 'false' }},
        };
    </script>

    @if (config('services.google_maps.api_key'))
        <script>
            (g=>{var h,a,k,p="The Google Maps JavaScript API",c="google",l="importLibrary",q="__ib__",m=document,b=window;b=b[c]||(b[c]={});var d=b.maps||(b.maps={}),r=new Set,e=new URLSearchParams,u=()=>h||(h=new Promise(async(f,n)=>{await (a=m.createElement("script"));e.set("libraries",[...r]+"");for(k in g)e.set(k.replace(/[A-Z]/g,t=>"_"+t[0].toLowerCase()),g[k]);e.set("callback",c+".maps."+q);a.src=`https://maps.${c}apis.com/maps/api/js?`+e;d[q]=f;a.onerror=()=>h=n(Error(p+" could not load."));a.nonce=m.querySelector("script[nonce]")?.nonce||"";m.head.append(a)}));d[l]?console.warn(p+" only loads once. Ignoring:",g):d[l]=(f,...n)=>r.add(f)&&u().then(()=>d[l](f,...n))})({
                key: "{{ config('services.google_maps.api_key') }}",
                v: "weekly",
            });
        </script>
    @endif

    @if (app()->environment('production') && config('services.google_analytics.measurement_id'))
        @php($gaId = config('services.google_analytics.measurement_id'))
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '{{ $gaId }}');
        </script>
    @endif

    @stack('scripts')
</head>
<body class="font-sans antialiased">
    <div data-banner-host class="lg:hidden sticky top-0 z-50 bg-white dark:bg-gray-950 border-b border-gray-200 dark:border-gray-800">
        <div class="max-w-7xl mx-auto px-3 py-2 flex items-center gap-3">
            <button
                type="button"
                data-banner-dismiss
                aria-label="Dismiss"
                class="shrink-0 p-1 -ml-1 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
            <img src="/favicon.svg" alt="" class="shrink-0 w-10 h-10 rounded-lg" />
            <div class="flex-1 min-w-0">
                <div class="font-semibold text-gray-900 dark:text-white text-sm leading-tight">RentConnectPH</div>
            </div>
            <a
                href="{{ config('services.app_store.android_url') }}"
                target="_blank"
                rel="noopener"
                data-banner-open
                data-android-package="{{ config('services.app_store.android_package') }}"
                class="shrink-0 bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors"
            >
                Get the app
            </a>
        </div>
    </div>
    <script>
        (function () {
            var host = document.querySelector('[data-banner-host]');
            if (!host) return;
            var ua = navigator.userAgent || '';
            var isAndroid = /Android/i.test(ua);
            if (!isAndroid) {
                host.remove();
                return;
            }
            var dismissed = document.cookie.split('; ').some(function (c) {
                return c === 'app_banner_dismissed=1';
            });
            if (dismissed) {
                host.remove();
                return;
            }
            var dismissBtn = host.querySelector('[data-banner-dismiss]');
            if (dismissBtn) {
                dismissBtn.addEventListener('click', function () {
                    document.cookie = 'app_banner_dismissed=1; path=/; SameSite=Lax';
                    host.remove();
                });
            }

            var openBtn = host.querySelector('[data-banner-open]');
            if (openBtn && 'getInstalledRelatedApps' in navigator) {
                navigator.getInstalledRelatedApps().then(function (apps) {
                    if (apps.length > 0) {
                        var fallback = openBtn.href;
                        var pkg = openBtn.getAttribute('data-android-package');
                        openBtn.setAttribute('href', 'intent://' + location.host + location.pathname + location.search
                            + '#Intent;scheme=https;package=' + pkg
                            + ';S.browser_fallback_url=' + encodeURIComponent(fallback) + ';end');
                        openBtn.removeAttribute('target');
                        openBtn.removeAttribute('rel');
                        openBtn.textContent = 'Open app';
                    }
                }).catch(function () {});
            }
        })();
    </script>
    <div id="app" data-page="@yield('page', 'home')"></div>
</body>
</html>
