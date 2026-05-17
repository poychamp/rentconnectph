<script setup>
const props = defineProps({
    listing:     { type: Object, required: true },
    sourceSites: { type: Array, required: true },
});

const lockedInputClass = 'mt-1 w-full rounded-md border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 px-3.5 py-2.5 text-sm text-gray-900 dark:text-white cursor-not-allowed';
</script>

<template>
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm p-6 space-y-5">
        <div>
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Source</h3>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Where this listing was originally found. Set by the calls team — read-only on this surface.
            </p>
        </div>

        <div>
            <label for="source-site" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Source site</label>
            <select
                id="source-site"
                :value="listing.source_site"
                disabled
                :class="lockedInputClass"
            >
                <option value="">— None —</option>
                <option v-for="s in sourceSites" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
        </div>

        <div>
            <label for="source-url" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Source URL</label>
            <input
                id="source-url"
                :value="listing.source_url"
                readonly
                type="url"
                :class="lockedInputClass"
            >
        </div>
    </div>
</template>
