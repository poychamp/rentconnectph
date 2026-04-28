<script setup>
defineProps({ description: { type: String, default: '' } });
</script>

<template>
    <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-5 py-4">
        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Description</h3>
        <p v-if="description" class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap max-h-32 overflow-y-auto">{{ description }}</p>
        <p v-else class="text-sm text-gray-400">No description</p>
    </div>
</template>
