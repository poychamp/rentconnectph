<script setup>
import DarkModeToggle from './DarkModeToggle.vue';

defineEmits(['back']);
</script>

<template>
    <header class="sticky top-0 z-40 bg-white dark:bg-gray-900 border-b border-gray-100 dark:border-gray-800">
        <div class="h-14 px-4 flex items-center">
            <button
                @click="$emit('back')"
                aria-label="Back"
                class="w-9 h-9 grid place-items-center rounded-full text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition"
            >
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M15 19l-7-7 7-7" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>
            <a href="/" class="flex-1 flex items-center justify-center gap-2">
                <span class="relative inline-flex items-center justify-center w-7 h-7 rounded-md bg-orange-500 text-white">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3 2 12h3v8h6v-6h2v6h6v-8h3z"/></svg>
                </span>
                <span class="text-base font-semibold tracking-tight text-gray-900 dark:text-white">
                    RentConnect<span class="text-orange-500">PH</span>
                </span>
            </a>
            <DarkModeToggle />
        </div>
    </header>
</template>
