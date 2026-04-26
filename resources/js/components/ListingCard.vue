<script setup>
import { computed } from 'vue';

const props = defineProps({
    listing: { type: Object, required: true },
});

const formattedPrice = computed(() => {
    const formatted = new Intl.NumberFormat('en-PH').format(props.listing.price_monthly);
    return `₱${formatted}`;
});
</script>

<template>
    <a :href="`/listings/${listing.uuid}`" class="block">
        <article class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 overflow-hidden hover:shadow-md dark:hover:shadow-black/40 transition cursor-pointer">
        <div class="relative aspect-video bg-gray-100 dark:bg-gray-800">
            <img :src="listing.image" :alt="listing.title" class="w-full h-full object-cover" />
            <span class="absolute top-3 left-3 inline-flex items-center gap-1 bg-emerald-500 text-white text-[10px] font-bold uppercase tracking-wider px-2 py-1 rounded">
                <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.707-9.293-4.5 4.5a1 1 0 0 1-1.414 0l-2-2a1 1 0 1 1 1.414-1.414L8.5 11.086l3.793-3.793a1 1 0 0 1 1.414 1.414Z" clip-rule="evenodd"/></svg>
                Verified
            </span>
            <span class="absolute bottom-3 right-3 bg-black/60 text-white text-xs font-medium px-2 py-1 rounded">
                {{ listing.image_count }} {{ listing.image_count === 1 ? 'photo' : 'photos' }}
            </span>
        </div>

        <div class="p-4">
            <div class="flex items-start justify-between gap-3">
                <h3 class="font-semibold text-gray-900 dark:text-white leading-snug truncate">{{ listing.title }}</h3>
                <span class="shrink-0 text-[11px] font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 rounded-full px-2 py-0.5">
                    {{ listing.type_label }}
                </span>
            </div>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 inline-flex items-center gap-1">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5Z"/></svg>
                {{ listing.barangay_label }}, CDO
            </p>
            <p class="mt-3 text-lg font-bold">
                <span class="text-orange-500">{{ formattedPrice }}</span>
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">/mo</span>
            </p>
            <div class="mt-3 flex items-center gap-4 text-xs text-gray-600 dark:text-gray-400">
                <span class="inline-flex items-center gap-1">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M7 14a3 3 0 1 1 0-6 3 3 0 0 1 0 6Zm14-4h-9v4H4V8H2v10h2v-2h16v2h2V12a2 2 0 0 0-1-1.73Z"/></svg>
                    {{ listing.beds }} bed
                </span>
                <span class="inline-flex items-center gap-1">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M5 12V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v1H7v6H5Zm-2 1h18v3a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4v-3Zm15-6V5a3 3 0 0 0-1.5-2.6L15 4.4l1 1 1.6-1A1 1 0 0 1 18 5v2h-2v.99h2V7Z"/></svg>
                    {{ listing.baths }} bath
                </span>
                <span class="inline-flex items-center gap-1">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="1"/></svg>
                    {{ listing.sqft }} sqm
                </span>
            </div>
        </div>
        </article>
    </a>
</template>
