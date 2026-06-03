<script setup>
import { ref, watch } from 'vue';

const emit = defineEmits(['search']);

const initialQuery = new URLSearchParams(window.location.search).get('q') ?? '';
const query = ref(initialQuery);

let debounceTimer = null;
watch(query, (newValue) => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        emit('search', newValue.trim());
    }, 500);
});
</script>

<template>
    <div class="flex items-center justify-between gap-4">
        <div class="relative flex-1 max-w-sm">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8" />
                <path d="m21 21-4.3-4.3" />
            </svg>
            <input
                v-model="query"
                type="search"
                placeholder="Search unverified listings…"
                class="w-full rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 pl-10 pr-3 py-2 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none"
            >
        </div>
    </div>
</template>
