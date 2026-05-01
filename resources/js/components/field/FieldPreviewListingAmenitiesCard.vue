<script setup>
defineProps({ amenities: { type: Array, required: true } });
</script>

<template>
    <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-5 py-4">
        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Amenities</h3>
        <div v-if="amenities.length" class="flex flex-wrap gap-1.5">
            <span
                v-for="a in amenities"
                :key="a.id"
                class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-orange-50 text-orange-700 dark:bg-orange-950/30 dark:text-orange-300"
            >{{ a.name }}</span>
        </div>
        <p v-else class="text-sm text-gray-400">No amenities listed</p>
    </div>
</template>
