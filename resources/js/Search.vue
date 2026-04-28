<script setup>
import { computed, onBeforeUnmount, onMounted, provide, ref } from 'vue';
import Navbar from './components/Navbar.vue';
import Footer from './components/Footer.vue';
import BottomNav from './components/BottomNav.vue';
import SearchBar from './components/SearchBar.vue';
import SearchTypeFilter from './components/SearchTypeFilter.vue';
import SearchResults from './components/SearchResults.vue';

const initial = window.__INITIAL_SEARCH__ ?? {
    listings: [],
    pagination: { current_page: 1, last_page: 1, per_page: 24, total: 0, from: null, to: null },
    listingTypes: [],
    barangays: [],
    filters: { q: '', budget: '', area: '', type: [] },
};

const filters = initial.filters ?? { q: '', budget: '', area: '', type: [] };

const hasActiveFilters = computed(() => {
    return !!(
        (filters.q && filters.q.trim()) ||
        filters.budget ||
        filters.area ||
        (Array.isArray(filters.type) && filters.type.length > 0)
    );
});

const total = computed(() => initial.pagination?.total ?? 0);

const headerSubtitle = computed(() => {
    if (total.value === 0 && !hasActiveFilters.value) return 'No verified listings yet — check back soon.';
    if (total.value === 0 &&  hasActiveFilters.value) return 'No matches — try widening your search.';
    if (hasActiveFilters.value) {
        return `Showing ${total.value.toLocaleString()} verified ${total.value === 1 ? 'listing' : 'listings'} matching your filters.`;
    }
    if (total.value === 1) return 'Browse 1 verified listing in Cagayan de Oro.';
    return `Browse ${total.value.toLocaleString()} verified listings across Cagayan de Oro.`;
});

function submitFilters(patch) {
    const next = { ...filters, ...patch };
    const params = new URLSearchParams();
    if (next.q && String(next.q).trim()) params.set('q', String(next.q).trim());
    if (next.budget)                      params.set('budget', next.budget);
    if (next.area)                        params.set('area', next.area);
    if (Array.isArray(next.type) && next.type.length > 0) {
        params.set('type', next.type.join(','));
    }
    const qs = params.toString();
    window.location.assign(qs ? `/search?${qs}` : '/search');
}

provide('search-submit', submitFilters);

const showScrollTop = ref(false);

function onScroll() {
    showScrollTop.value = window.scrollY > 400;
}

function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
});
</script>

<template>
    <div class="min-h-screen flex flex-col bg-white dark:bg-gray-950 text-gray-900 dark:text-gray-100 pb-20 md:pb-0">
        <Navbar />

        <section class="relative isolate overflow-hidden text-white">
            <div class="absolute inset-0 bg-[linear-gradient(135deg,#ff8c42_0%,#e5722b_55%,#7a2d0a_100%)] dark:bg-[linear-gradient(135deg,#3a1d0e_0%,#2a1408_55%,#160901_100%)]"></div>
            <div class="absolute inset-0 mix-blend-multiply dark:opacity-30 bg-[radial-gradient(circle_at_80%_20%,rgba(255,200,140,0.35)_0%,rgba(255,140,66,0)_55%)]"></div>

            <div class="relative max-w-7xl mx-auto w-full px-4 md:px-6 lg:px-8 py-10 md:py-14">
                <div class="flex items-center gap-3 md:gap-4">
                    <div class="shrink-0 w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-amber-200 dark:bg-amber-300/90 shadow-lg ring-1 ring-white/30 dark:ring-white/10 flex items-center justify-center">
                        <svg class="w-6 h-6 md:w-7 md:h-7 text-orange-700 dark:text-orange-900" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="7" />
                            <path d="m20 20-3.5-3.5" />
                        </svg>
                    </div>
                    <h1 class="text-3xl md:text-4xl lg:text-5xl font-bold tracking-tight">
                        Search Listings
                    </h1>
                </div>
                <p class="mt-3 text-sm md:text-base text-orange-50/90 dark:text-orange-100/80">
                    {{ headerSubtitle }}
                </p>

                <div class="mt-6 md:mt-8">
                    <SearchBar
                        :initial="filters"
                        :barangays="initial.barangays"
                        :listing-types="initial.listingTypes"
                    />
                </div>
            </div>
        </section>

        <main class="flex-1 max-w-7xl mx-auto w-full px-4 md:px-6 lg:px-8 py-6 md:py-8">
            <SearchTypeFilter
                :initial="filters.type"
                :listing-types="initial.listingTypes"
            />
            <SearchResults
                :listings="initial.listings"
                :pagination="initial.pagination"
                :has-active-filters="hasActiveFilters"
            />
        </main>

        <Footer />
        <BottomNav />

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-2"
        >
            <button
                v-if="showScrollTop"
                @click="scrollToTop"
                aria-label="Scroll to top"
                class="md:hidden fixed bottom-24 right-4 z-40 w-12 h-12 rounded-full bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white shadow-xl ring-2 ring-white dark:ring-gray-900 flex items-center justify-center"
            >
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m18 15-6-6-6 6"/>
                </svg>
            </button>
        </Transition>
    </div>
</template>
