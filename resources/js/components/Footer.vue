<template>
    <footer class="hidden md:flex border-t border-gray-100 dark:border-gray-800 mt-16 bg-white dark:bg-gray-900">
        <div class="max-w-7xl mx-auto w-full px-4 md:px-6 lg:px-8 py-6 md:py-8 flex flex-col md:flex-row md:items-center md:justify-between gap-3 md:gap-4">
            <a href="/" class="flex items-center gap-2">
                <span class="relative inline-flex items-center justify-center w-8 h-8 rounded-md bg-orange-500 text-white">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3 2 12h3v8h6v-6h2v6h6v-8h3z"/></svg>
                    <span class="absolute -top-0.5 -right-0.5 w-3 h-3 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-gray-900 grid place-items-center">
                        <svg class="w-2 h-2 text-white" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/>
                        </svg>
                    </span>
                </span>
                <span class="text-base font-semibold tracking-tight text-gray-900 dark:text-white">
                    RentConnect<span class="text-orange-500">PH</span>
                </span>
            </a>
            <div class="flex flex-wrap items-center gap-x-3 md:gap-x-4 gap-y-1 text-xs md:text-sm text-gray-500 dark:text-gray-400">
                <a href="/privacy" class="hover:text-orange-600 dark:hover:text-orange-400">Privacy</a>
                <span aria-hidden="true" class="text-gray-300 dark:text-gray-700">·</span>
                <a href="/terms" class="hover:text-orange-600 dark:hover:text-orange-400">Terms</a>
                <span aria-hidden="true" class="text-gray-300 dark:text-gray-700">·</span>
                <p>© 2026 RentConnectPH · Cagayan de Oro · Philippines</p>
            </div>
        </div>
    </footer>
</template>
