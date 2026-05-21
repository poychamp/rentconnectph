<script setup>
import ListingCard from './ListingCard.vue';

defineProps({
    title: { type: String, required: true },
    subtitleDesktop: { type: String, default: '' },
    subtitleMobile: { type: String, default: '' },
    listings: { type: Array, default: () => [] },
    showViewAll: { type: Boolean, default: false },
});
</script>

<template>
    <section>
        <div class="flex items-center justify-between gap-4 mb-4">
            <div>
                <h2 class="text-xl md:text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{ title }}</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    <span class="md:hidden">{{ subtitleMobile }}</span>
                    <span class="hidden md:inline">{{ subtitleDesktop }}</span>
                </p>
            </div>
            <a
                v-if="showViewAll"
                href="/search"
                class="shrink-0 inline-flex items-center justify-center gap-1 bg-transparent hover:bg-orange-50 dark:hover:bg-orange-950 border border-orange-500 text-orange-500 text-xs font-semibold px-3 py-1.5 rounded-lg transition duration-[80ms] ease-out active:opacity-50 cursor-pointer"
            >
                View all →
            </a>
        </div>

        <div v-if="listings.length === 0" class="text-sm text-gray-500 dark:text-gray-400 py-12 text-center">
            No listings match this filter.
        </div>
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <ListingCard v-for="listing in listings" :key="listing.id" :listing="listing" />
        </div>
    </section>
</template>
