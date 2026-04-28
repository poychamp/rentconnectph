<script setup>
import { computed } from 'vue';

const props = defineProps({ listing: { type: Object, required: true } });

const formattedPrice = computed(() =>
    new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 })
        .format(props.listing.price_monthly ?? 0)
);
</script>

<template>
    <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-5 py-4">
        <div class="flex items-start justify-between gap-3">
            <div class="flex-1 min-w-0">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white tracking-tight">{{ listing.title }}</h2>
                <p class="mt-1">
                    <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ listing.type_label }}</span>
                </p>
            </div>
            <p class="text-xl font-semibold text-gray-900 dark:text-white shrink-0">{{ formattedPrice }}<span class="text-xs font-normal text-gray-500 dark:text-gray-400">/mo</span></p>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-1 text-sm text-gray-600 dark:text-gray-400">
            <span><span class="font-medium text-gray-900 dark:text-gray-100">{{ listing.beds }}</span> {{ listing.beds === 1 ? 'bed' : 'beds' }}</span>
            <span><span class="font-medium text-gray-900 dark:text-gray-100">{{ listing.baths }}</span> {{ listing.baths === 1 ? 'bath' : 'baths' }}</span>
            <span><span class="font-medium text-gray-900 dark:text-gray-100">{{ listing.sqm }}</span> sqm</span>
        </div>
    </div>
</template>
