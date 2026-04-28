<script setup>
const path = typeof window !== 'undefined' ? window.location.pathname : '/';

const tabs = [
    { label: 'Home',   icon: 'home',   href: '/',       match: (p) => p === '/' },
    { label: 'Search', icon: 'search', href: '/search', match: (p) => p.startsWith('/search') },
    { label: 'About',  icon: 'info',   href: '/about',  match: (p) => p.startsWith('/about') },
];
</script>

<template>
    <nav class="md:hidden fixed bottom-0 inset-x-0 bg-white dark:bg-gray-900 border-t border-gray-100 dark:border-gray-800 z-40">
        <div class="grid grid-cols-3">
            <a
                v-for="tab in tabs"
                :key="tab.label"
                :href="tab.href"
                @click="(e) => { if (tab.href === '#') e.preventDefault(); }"
                class="flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium"
                :class="tab.match(path) ? 'text-orange-500' : 'text-gray-500 dark:text-gray-400'"
            >
                <svg v-if="tab.icon === 'home'" class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3 2 12h3v8h6v-6h2v6h6v-8h3z"/></svg>
                <svg v-else-if="tab.icon === 'search'" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <svg v-else class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                {{ tab.label }}
            </a>
        </div>
    </nav>
</template>
