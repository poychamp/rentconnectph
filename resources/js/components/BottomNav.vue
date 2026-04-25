<script setup>
import { ref } from 'vue';

const active = ref('Home');
const tabs = [
    { label: 'Home', icon: 'home' },
    { label: 'Saved', icon: 'heart' },
    { label: 'Messages', icon: 'chat' },
    { label: 'About', icon: 'info' },
];
</script>

<template>
    <nav class="md:hidden fixed bottom-0 inset-x-0 bg-white dark:bg-gray-900 border-t border-gray-100 dark:border-gray-800 z-40">
        <div class="grid grid-cols-4">
            <a
                v-for="tab in tabs"
                :key="tab.label"
                href="#"
                @click.prevent="active = tab.label"
                class="flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium"
                :class="active === tab.label ? 'text-orange-500' : 'text-gray-500 dark:text-gray-400'"
            >
                <svg v-if="tab.icon === 'home'" class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3 2 12h3v8h6v-6h2v6h6v-8h3z"/></svg>
                <svg v-else-if="tab.icon === 'heart'" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-4.35-7-10a4.5 4.5 0 0 1 8.4-2.3L12 9l-1.4-.3A4.5 4.5 0 0 1 19 11c0 5.65-7 10-7 10Z" stroke-linejoin="round"/></svg>
                <svg v-else-if="tab.icon === 'chat'" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5Z" stroke-linejoin="round"/></svg>
                <svg v-else class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                {{ tab.label }}
            </a>
        </div>
    </nav>
</template>
