<script setup>
import { computed } from 'vue';
import ListingCard from './ListingCard.vue';

const props = defineProps({
    listings:         { type: Array,   required: true },
    pagination:       { type: Object,  required: true },
    hasActiveFilters: { type: Boolean, default: false },
});

const hasResults = computed(() => props.listings.length > 0);
const hasPrev    = computed(() => props.pagination.current_page > 1);
const hasNext    = computed(() => props.pagination.current_page < props.pagination.last_page);

function buildHref(page) {
    if (typeof window === 'undefined') return `?page=${page}`;
    const params = new URLSearchParams(window.location.search);
    params.set('page', String(page));
    return `?${params.toString()}`;
}

const prevHref = computed(() => buildHref(props.pagination.current_page - 1));
const nextHref = computed(() => buildHref(props.pagination.current_page + 1));

const pageItems = computed(() => {
    const last = props.pagination.last_page;
    const current = props.pagination.current_page;

    if (last <= 7) {
        return Array.from({ length: last }, (_, i) => i + 1);
    }

    const items = new Set([1, last, current, current - 1, current + 1]);
    if (current <= 3)        [2, 3, 4].forEach(p => items.add(p));
    if (current >= last - 2) [last - 3, last - 2, last - 1].forEach(p => items.add(p));

    const sorted = [...items].filter(p => p >= 1 && p <= last).sort((a, b) => a - b);

    const result = [];
    sorted.forEach((p, i) => {
        if (i > 0 && p - sorted[i - 1] > 1) result.push('...');
        result.push(p);
    });
    return result;
});
</script>

<template>
    <div>
        <div v-if="!hasResults" class="text-center py-20">
            <template v-if="hasActiveFilters">
                <p class="text-gray-700 dark:text-gray-200 font-medium">No matches for your filters.</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Try widening your search or clear filters.</p>
                <a
                    href="/search"
                    class="mt-4 inline-flex items-center justify-center gap-1 bg-transparent hover:bg-orange-50 dark:hover:bg-orange-950 border border-orange-500 text-orange-500 text-sm font-semibold px-4 py-2 rounded-lg transition duration-[80ms] ease-out active:opacity-50 cursor-pointer"
                >
                    Clear all filters
                </a>
            </template>
            <template v-else>
                <p class="text-gray-500 dark:text-gray-400">No verified listings yet.</p>
            </template>
        </div>

        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-6">
            <ListingCard
                v-for="listing in listings"
                :key="listing.uuid"
                :listing="listing"
            />
        </div>

        <nav v-if="hasResults && pagination.last_page > 1"
            class="mt-10 flex items-center justify-center gap-1.5 text-sm flex-wrap">
            <a v-if="hasPrev"
                :href="prevHref"
                aria-label="Previous page"
                class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            </a>
            <span v-else aria-hidden="true"
                class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-gray-300 dark:text-gray-700 border border-gray-100 dark:border-gray-800">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            </span>

            <template v-for="(item, idx) in pageItems" :key="idx">
                <span v-if="item === '...'"
                    class="inline-flex items-center justify-center w-9 h-9 text-gray-400 dark:text-gray-600 select-none">
                    …
                </span>
                <a v-else-if="item !== pagination.current_page"
                    :href="buildHref(item)"
                    :aria-label="`Page ${item}`"
                    class="inline-flex items-center justify-center min-w-9 h-9 px-3 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 font-medium">
                    {{ item }}
                </a>
                <span v-else
                    :aria-current="'page'"
                    class="inline-flex items-center justify-center min-w-9 h-9 px-3 rounded-lg bg-orange-500 dark:bg-orange-600 text-white border border-orange-500 dark:border-orange-600 font-semibold shadow-sm">
                    {{ item }}
                </span>
            </template>

            <a v-if="hasNext"
                :href="nextHref"
                aria-label="Next page"
                class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            </a>
            <span v-else aria-hidden="true"
                class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-gray-300 dark:text-gray-700 border border-gray-100 dark:border-gray-800">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            </span>

            <span class="hidden sm:inline-flex items-center ml-2 text-xs text-gray-500 dark:text-gray-400">
                {{ pagination.from?.toLocaleString() }}–{{ pagination.to?.toLocaleString() }}
                of {{ pagination.total?.toLocaleString() }}
            </span>
        </nav>
    </div>
</template>
