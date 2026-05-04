<script setup>
defineProps({
    active: {
        type: String,
        required: true,
        validator: (v) => ['filtered', 'all'].includes(v),
    },
    counts: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <nav class="border-b border-gray-200 dark:border-gray-800 mb-6">
        <div class="flex gap-6">
            <a
                href="/admin/filtered-inquiries"
                class="px-1 py-3 text-sm font-medium border-b-2 -mb-px transition-colors"
                :class="active === 'filtered'
                    ? 'border-orange-500 text-gray-900 dark:text-white'
                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
            >
                Filtered Inquiries
                <span class="ml-1 text-xs text-gray-500 dark:text-gray-400">({{ counts.filtered }})</span>
            </a>
            <a
                href="/admin/inquiries"
                class="px-1 py-3 text-sm font-medium border-b-2 -mb-px transition-colors"
                :class="active === 'all'
                    ? 'border-orange-500 text-gray-900 dark:text-white'
                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
            >
                All Inquiries
                <span class="ml-1 text-xs text-gray-500 dark:text-gray-400">({{ counts.all }})</span>
            </a>
        </div>
    </nav>
</template>
