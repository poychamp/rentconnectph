<script setup>
import { computed } from 'vue';

defineProps({
    firstName: { type: String, required: true },
});

const dateLine = computed(() => {
    return new Intl.DateTimeFormat('en-US', {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
    }).format(new Date()).toUpperCase();
});

const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour >= 5 && hour < 12) return 'Good morning';
    if (hour >= 12 && hour < 18) return 'Good afternoon';
    return 'Good evening';
});
</script>

<template>
    <div class="max-w-3xl">
        <p class="text-xs uppercase tracking-widest text-gray-400">{{ dateLine }}</p>
        <h1 class="mt-2 text-4xl md:text-5xl font-bold text-gray-900 dark:text-white tracking-tight">
            {{ greeting }}, {{ firstName }}.
        </h1>
        <p class="mt-3 text-base text-gray-500 dark:text-gray-400">
            Welcome back to RentConnectPH. Pick a section from the sidebar to get started.
        </p>
    </div>
</template>
