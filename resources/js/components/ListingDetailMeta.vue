<script setup>
import { computed, inject } from 'vue';
import ListingDetailShareCluster from './ListingDetailShareCluster.vue';

const props = defineProps({
    listing: { type: Object, required: true },
    hideCta: { type: Boolean, default: false },
});

const inquireModalOpen = inject('inquireModalOpen', null);

function openInquireModal() {
    if (inquireModalOpen) inquireModalOpen.value = true;
}

const formattedPrice = computed(() => {
    const n = new Intl.NumberFormat('en-PH').format(props.listing.price_monthly);
    return `₱${n}`;
});

const verifiedDateLabel = computed(() => {
    if (!props.listing.listed_at) return '';
    const d = new Date(props.listing.listed_at);
    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    }).format(d);
});
</script>

<template>
    <div :class="hideCta ? 'pt-3' : 'bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-2xl p-6'">
        <div class="flex items-start justify-between gap-2">
            <span class="inline-flex items-center gap-1 bg-emerald-500 text-white text-[10px] font-bold uppercase tracking-wider px-2 py-1 rounded">
                <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.707-9.293-4.5 4.5a1 1 0 0 1-1.414 0l-2-2a1 1 0 1 1 1.414-1.414L8.5 11.086l3.793-3.793a1 1 0 0 1 1.414 1.414Z" clip-rule="evenodd"/></svg>
                Verified
            </span>
            <ListingDetailShareCluster :listing="listing" />
        </div>

        <h1 class="mt-3 text-2xl font-bold text-gray-900 dark:text-white leading-tight">
            {{ listing.title }}
        </h1>

        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400 inline-flex items-center gap-1">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5Z"/></svg>
            {{ listing.barangay_label }}, Cagayan de Oro
        </p>

        <div class="mt-4 flex items-end justify-between gap-3">
            <div>
                <p class="text-2xl font-bold text-orange-500">{{ formattedPrice }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">per month</p>
            </div>
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ listing.type_label }}</span>
        </div>

        <div class="mt-5 hidden md:flex items-center gap-4 text-sm text-gray-700 dark:text-gray-300 border-t border-gray-100 dark:border-gray-800 pt-4">
            <span v-if="listing.beds" class="inline-flex items-center gap-1">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M7 14a3 3 0 1 1 0-6 3 3 0 0 1 0 6Zm14-4h-9v4H4V8H2v10h2v-2h16v2h2V12a2 2 0 0 0-1-1.73Z"/></svg>
                {{ listing.beds }} beds
            </span>
            <span v-if="listing.baths" class="inline-flex items-center gap-1">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M5 12V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v1H7v6H5Zm-2 1h18v3a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4v-3Zm15-6V5a3 3 0 0 0-1.5-2.6L15 4.4l1 1 1.6-1A1 1 0 0 1 18 5v2h-2v.99h2V7Z"/></svg>
                {{ listing.baths }} baths
            </span>
            <span v-if="listing.sqm" class="inline-flex items-center gap-1">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="1"/></svg>
                {{ listing.sqm }} sqm
            </span>
        </div>

        <button
            v-if="!hideCta"
            type="button"
            @click="openInquireModal"
            class="mt-6 w-full inline-flex items-center justify-center gap-2 bg-orange-500 hover:bg-orange-600 text-white font-medium rounded-xl px-5 py-3 text-sm transition cursor-pointer"
        >
            Get Contact
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg>
        </button>

        <p v-if="verifiedDateLabel" class="mt-4 text-xs text-emerald-600 dark:text-emerald-400 inline-flex items-center gap-1">
            <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
            Verified on {{ verifiedDateLabel }}
        </p>

        <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">
            Owner contact never shown publicly
        </p>
    </div>
</template>
