<script setup>
import { onBeforeUnmount, provide, reactive, watch } from 'vue';
import BudgetRangeSlider from './BudgetRangeSlider.vue';

const props = defineProps({
    barangays: { type: Array, default: () => [] },
});

const heroImage = window.__ASSETS__?.hero;

// Local reactive state — same shape as Search.vue's `live` so BudgetRangeSlider
// (which injects 'search-live') works identically on both surfaces.
const live = reactive({
    q:          '',
    budget_min: null,
    budget_max: null,
    area:       '',
});

provide('search-live', live);

const SEARCH_DEBOUNCE_MS = 1000;
let debounceTimer = null;

function clearDebounce() {
    if (debounceTimer !== null) {
        clearTimeout(debounceTimer);
        debounceTimer = null;
    }
}

function navigate() {
    const params = new URLSearchParams();
    const q = String(live.q || '').trim();
    if (q) params.set('q', q);
    if (live.budget_min !== null && live.budget_min !== '') params.set('budget_min', String(live.budget_min));
    if (live.budget_max !== null && live.budget_max !== '') params.set('budget_max', String(live.budget_max));
    if (live.area) params.set('area', live.area);
    const qs = params.toString();
    window.location.assign(qs ? `/search?${qs}` : '/search');
}

function scheduleSubmit() {
    clearDebounce();
    debounceTimer = setTimeout(() => {
        debounceTimer = null;
        navigate();
    }, SEARCH_DEBOUNCE_MS);
}

function submitNow(patch = {}) {
    if (patch.q !== undefined)          live.q = patch.q;
    if (patch.budget_min !== undefined) live.budget_min = patch.budget_min;
    if (patch.budget_max !== undefined) live.budget_max = patch.budget_max;
    if (patch.area !== undefined)       live.area = patch.area;
    clearDebounce();
    navigate();
}

provide('search-submit', submitNow);

// Expose debounce-cancel downward so BudgetRangeSlider can pause the pending
// q/area auto-submit while its popover is open.
provide('search-cancel-debounce', () => clearDebounce());

function submit() {
    clearDebounce();
    navigate();
}

watch([() => live.q, () => live.budget_min, () => live.budget_max, () => live.area], scheduleSubmit);

onBeforeUnmount(clearDebounce);
</script>

<template>
    <section class="relative isolate overflow-hidden text-white bg-[#1b1410]">
        <img
            :src="heroImage"
            alt="Cagayan de Oro skyline at dusk"
            class="absolute inset-0 w-full h-full object-cover"
        />
        <div
            class="absolute inset-0 mix-blend-multiply"
            style="background: linear-gradient(135deg, rgba(255,140,66,0.78) 0%, rgba(229,114,43,0.72) 55%, rgba(122,45,10,0.85) 100%);"
        ></div>
        <div
            class="absolute inset-0"
            style="background: linear-gradient(rgba(0,0,0,0.15) 0%, rgba(0,0,0,0) 40%, rgba(0,0,0,0.35) 100%);"
        ></div>
        <div class="relative max-w-7xl mx-auto px-4 md:px-6 lg:px-8 py-12 md:py-20 lg:py-28">
            <p class="text-xs md:text-sm font-semibold tracking-widest uppercase text-[#ffe3b0]">
                <span class="md:hidden">Cagayan de Oro</span>
                <span class="hidden md:inline">Cagayan de Oro · Misamis Oriental · Philippines</span>
            </p>
            <h1 class="mt-3 text-3xl md:text-5xl lg:text-6xl font-bold leading-tight max-w-3xl">
                Find your next home in CDO
            </h1>
            <p class="mt-4 text-base md:text-lg text-gray-200 max-w-2xl">
                <span class="md:hidden">Verified listings you can trust.</span>
                <span class="hidden md:inline">Verified listings you can trust. Every property is hand-checked by our team before it goes live.</span>
            </p>

            <div class="mt-8 bg-white text-gray-900 rounded-2xl p-3 md:p-2 md:pl-4 flex flex-col md:flex-row md:items-center gap-2 shadow-xl max-w-3xl">
                <input
                    v-model="live.q"
                    @keydown.enter="submit"
                    type="text"
                    placeholder="keywords, amenities (e.g. wifi), 1 bed, 2 baths, 30sqm"
                    class="flex-1 bg-transparent outline-none px-2 py-2 text-sm md:text-base placeholder:text-xs md:placeholder:text-sm"
                />
                <div class="flex gap-2 md:contents">
                    <BudgetRangeSlider class="flex-1 md:flex-none" />
                    <select v-model="live.area" class="flex-1 md:flex-none md:w-44 bg-gray-50 md:bg-transparent rounded-lg px-3 py-2 text-sm border border-gray-200 md:border-0 outline-none">
                        <option value="">CDO Areas</option>
                        <option v-for="a in barangays" :key="a.value" :value="a.value">{{ a.label }}</option>
                    </select>
                </div>
                <button
                    type="button"
                    @click="submit"
                    class="hidden md:inline-flex items-center justify-center bg-orange-500 hover:bg-orange-600 text-white font-medium rounded-xl px-6 py-3 text-sm transition cursor-pointer"
                >
                    Search
                </button>
            </div>

            <ul class="hidden md:flex mt-6 flex-wrap gap-x-6 gap-y-2 text-sm text-gray-200">
                <li class="inline-flex items-center gap-2"><span class="text-orange-400">✓</span> Licensed broker</li>
                <li class="inline-flex items-center gap-2"><span class="text-orange-400">✓</span> No login needed</li>
            </ul>
        </div>
    </section>
</template>
