<script setup>
defineProps({
    listing:   { type: Object, required: true },
    mapboxUrl: { type: String, default: null },
});
</script>

<template>
    <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm p-6">
        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Location</h3>
        <p class="text-sm text-gray-900 dark:text-gray-100">{{ listing.barangay_label || '—' }}</p>
        <div v-if="mapboxUrl" class="mt-3 rounded-md overflow-hidden border border-gray-200 dark:border-gray-800 h-32 bg-gray-100 dark:bg-gray-800">
            <img :src="mapboxUrl" alt="Listing location map" class="w-full h-full object-cover" />
        </div>
    </div>
</template>
