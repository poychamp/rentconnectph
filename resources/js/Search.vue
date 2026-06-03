<script setup>
import { computed, onBeforeUnmount, onMounted, provide, reactive, ref } from 'vue';
import axios from './axios.js';
import Navbar from './components/Navbar.vue';
import Footer from './components/Footer.vue';
import BottomNav from './components/BottomNav.vue';
import SearchBar from './components/SearchBar.vue';
import SearchTypeFilter from './components/SearchTypeFilter.vue';
import SearchResults from './components/SearchResults.vue';

const initial = reactive(window.__INITIAL_SEARCH__ ?? {
    data: [],
    links: { first: null, last: null, prev: null, next: null },
    meta: { current_page: 1, last_page: 1, per_page: 24, total: 0, from: null, to: null },
    catalogs: { listing_types: [], barangays: [] },
    filters: { q: '', budget_min: null, budget_max: null, area: '', type: [] },
});

// Live reactive state — bound to SearchBar inputs (v-model) so submitFilters
// always reads what the user currently has on screen.
const live = reactive({
    q:          initial.filters?.q ?? '',
    budget_min: initial.filters?.budget_min ?? null,
    budget_max: initial.filters?.budget_max ?? null,
    area:       initial.filters?.area ?? '',
    type:       Array.isArray(initial.filters?.type) ? [...initial.filters.type] : [],
});

provide('search-live', live);

// Flag SearchBar reads to skip a debounced auto-submit when we programmatically
// update `live` (popstate sync). One-shot — flipped back to false after consumption.
const skipNextDebounce = ref(false);
provide('search-skip-next-debounce', skipNextDebounce);

const hasActiveFilters = computed(() => {
    const f = initial.filters ?? {};
    return !!(
        (f.q && String(f.q).trim()) ||
        f.budget_min !== null ||
        f.budget_max !== null ||
        f.area ||
        (Array.isArray(f.type) && f.type.length > 0)
    );
});

const total = computed(() => initial.meta?.total ?? 0);

const headerSubtitle = computed(() => {
    if (total.value === 0 && !hasActiveFilters.value) return 'No verified listings yet — check back soon.';
    if (total.value === 0 &&  hasActiveFilters.value) return 'No matches — try widening your search.';
    if (hasActiveFilters.value) {
        return `Showing ${total.value.toLocaleString()} verified ${total.value === 1 ? 'listing' : 'listings'} matching your filters.`;
    }
    if (total.value === 1) return 'Browse 1 verified listing in Cagayan de Oro.';
    return `Browse ${total.value.toLocaleString()} verified listings across Cagayan de Oro.`;
});

const loading = ref(false);
let pendingController = null;

function buildParamsFromLive() {
    const params = new URLSearchParams();
    const q = String(live.q || '').trim();
    if (q) params.set('q', q);
    if (live.budget_min !== null && live.budget_min !== '') params.set('budget_min', String(live.budget_min));
    if (live.budget_max !== null && live.budget_max !== '') params.set('budget_max', String(live.budget_max));
    if (live.area) params.set('area', live.area);
    if (Array.isArray(live.type) && live.type.length > 0) {
        params.set('type', live.type.join(','));
    }
    return params;
}

async function fetchAndApply(params, { pushHistory = true, scrollTop = false } = {}) {
    if (pendingController) pendingController.abort();
    pendingController = new AbortController();

    loading.value = true;
    try {
        const response = await axios.request({
            method: 'get',
            url: `/api/v1/search?${params.toString()}`,
            // Axios 1.x strips Content-Type on bodiless GETs; passing data: ''
            // forces it to keep the header, which the /api/v1/* RequireJsonHeaders
            // middleware requires per CLAUDE.md.
            data: '',
            headers: {
                'Accept':       'application/json',
                'Content-Type': 'application/json',
            },
            signal: pendingController.signal,
        });

        Object.assign(initial, response.data);

        if (pushHistory) {
            const qs = params.toString();
            window.history.pushState(null, '', qs ? `/search?${qs}` : '/search');
        }

        if (scrollTop) {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    } catch (err) {
        if (err.name === 'CanceledError' || axios.isCancel?.(err)) return;
        // Fallback to full reload so the user still gets results
        const qs = params.toString();
        window.location.assign(qs ? `/search?${qs}` : '/search');
    } finally {
        loading.value = false;
        pendingController = null;
    }
}

function submitFilters(patch = {}) {
    if (patch.q !== undefined)          live.q = patch.q;
    if (patch.budget_min !== undefined) live.budget_min = patch.budget_min;
    if (patch.budget_max !== undefined) live.budget_max = patch.budget_max;
    if (patch.area !== undefined)       live.area = patch.area;
    if (patch.type !== undefined)       live.type = patch.type;

    // Filter change resets pagination (no page param) — server defaults to page 1.
    const params = buildParamsFromLive();
    fetchAndApply(params, { pushHistory: true, scrollTop: false });
}

function navigateToPage(page) {
    const params = buildParamsFromLive();
    params.set('page', String(page));
    fetchAndApply(params, { pushHistory: true, scrollTop: true });
}

provide('search-submit', submitFilters);
provide('search-navigate-page', navigateToPage);

// Back/forward — re-sync live state from URL + refetch (no pushState — browser
// already moved the URL). Set skip flag so SearchBar's debounce doesn't fire
// a second submit after we programmatically rewrite live.
function onPopState() {
    const params = new URLSearchParams(window.location.search);

    skipNextDebounce.value = true;
    live.q          = params.get('q') ?? '';
    live.budget_min = params.get('budget_min') !== null ? Number(params.get('budget_min')) : null;
    live.budget_max = params.get('budget_max') !== null ? Number(params.get('budget_max')) : null;
    live.area       = params.get('area') ?? '';
    const type      = params.get('type');
    live.type       = type ? type.split(',').filter(Boolean) : [];

    fetchAndApply(params, { pushHistory: false, scrollTop: false });
}

const showScrollTop = ref(false);

function onScroll() {
    showScrollTop.value = window.scrollY > 400;
}

function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('popstate', onPopState);
    onScroll();
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
    window.removeEventListener('popstate', onPopState);
    if (pendingController) pendingController.abort();
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
                        :barangays="initial.catalogs?.barangays ?? []"
                        :listing-types="initial.catalogs?.listing_types ?? []"
                    />
                </div>
            </div>
        </section>

        <main class="flex-1 max-w-7xl mx-auto w-full px-4 md:px-6 lg:px-8 py-6 md:py-8">
            <SearchTypeFilter
                :initial="initial.filters?.type ?? []"
                :listing-types="initial.catalogs?.listing_types ?? []"
            />

            <div v-if="hasActiveFilters" class="flex justify-end -mt-1 mb-4">
                <button
                    type="button"
                    @click="submitFilters({ q: '', budget_min: null, budget_max: null, area: '', type: [] })"
                    class="inline-flex items-center justify-center gap-1 bg-transparent hover:bg-gray-100 dark:hover:bg-gray-800 border border-gray-600/50 dark:border-gray-300/50 text-gray-600 dark:text-gray-300 text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors cursor-pointer"
                >
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18M6 6l12 12"/>
                    </svg>
                    Clear filters
                </button>
            </div>

            <div v-if="loading" class="flex items-center justify-center py-20" aria-live="polite" aria-busy="true">
                <svg class="animate-spin w-10 h-10 text-orange-500" viewBox="0 0 24 24" fill="none" aria-label="Loading results">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-opacity="0.25"/>
                    <path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                </svg>
            </div>
            <SearchResults
                v-else
                :listings="initial.data"
                :pagination="initial.meta"
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
