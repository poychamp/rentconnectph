<script setup>
import { computed } from 'vue';

const dateLine = computed(() => {
    return new Intl.DateTimeFormat('en-US', {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
    }).format(new Date()).toUpperCase();
});
</script>

<template>
    <div class="max-w-3xl">
        <p class="text-xs uppercase tracking-widest text-gray-400">{{ dateLine }}</p>
        <h1 class="mt-2 text-4xl md:text-5xl font-bold text-gray-900 dark:text-white tracking-tight">
            Welcome.
        </h1>
        <p class="mt-3 text-base text-gray-500 dark:text-gray-400">
            Pick a section from the sidebar to get started.
        </p>
    </div>
</template>
