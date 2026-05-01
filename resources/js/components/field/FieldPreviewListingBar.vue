<script setup>
import { computed } from 'vue';

const props = defineProps({
    from: { type: String, default: 'submitted' },
});

const backHref = computed(() => props.from === 'verified'
    ? '/field/verified-listings'
    : '/field/submitted-listings');

const backLabel = computed(() => props.from === 'verified'
    ? 'Back to Verified'
    : 'Back to Submitted');
</script>

<template>
    <div class="shrink-0 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800 px-6 py-3 flex items-center gap-3">
        <a
            :href="backHref"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
        >
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 12H5M12 19l-7-7 7-7" />
            </svg>
            {{ backLabel }}
        </a>

        <div class="flex-1"></div>
    </div>
</template>
