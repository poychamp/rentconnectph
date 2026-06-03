<script setup>
import DarkModeToggle from './DarkModeToggle.vue';

const path = typeof window !== 'undefined' ? window.location.pathname : '/';

const links = [
    { label: 'Home',    href: '/',        match: (p) => p === '/' },
    { label: 'Search',  href: '/search',  match: (p) => p.startsWith('/search') },
    { label: 'About',   href: '/about',   match: (p) => p.startsWith('/about') },
    { label: 'Contact', href: '/contact', match: (p) => p.startsWith('/contact') },
];
</script>

<template>
    <header class="sticky top-0 z-40 bg-white dark:bg-gray-900 border-b border-gray-100 dark:border-gray-800">
        <div class="max-w-7xl mx-auto px-4 md:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="/" class="flex items-center gap-2">
                <span class="relative inline-flex items-center justify-center w-8 h-8 rounded-md bg-orange-500 text-white">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3 2 12h3v8h6v-6h2v6h6v-8h3z"/></svg>
                    <span class="absolute -top-0.5 -right-0.5 w-3 h-3 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-gray-900 grid place-items-center">
                        <svg class="w-2 h-2 text-white" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/>
                        </svg>
                    </span>
                </span>
                <span class="text-lg font-semibold tracking-tight text-gray-900 dark:text-white">
                    RentConnect<span class="text-orange-500">PH</span>
                </span>
            </a>
            <div class="flex items-center gap-2 md:gap-6">
                <nav class="hidden md:flex items-center gap-8 text-sm font-medium">
                    <a
                        v-for="link in links"
                        :key="link.label"
                        :href="link.href"
                        @click="(e) => { if (link.href === '#') e.preventDefault(); }"
                        :class="[link.match(path) ? 'text-orange-500' : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white']"
                    >
                        {{ link.label }}
                    </a>
                </nav>
                <DarkModeToggle />
            </div>
        </div>
    </header>
</template>
