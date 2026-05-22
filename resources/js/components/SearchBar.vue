<script setup>
import { computed, inject, onBeforeUnmount, provide, watch } from 'vue';
import BudgetRangeSlider from './BudgetRangeSlider.vue';

const props = defineProps({
    barangays:    { type: Array, default: () => [] },
    listingTypes: { type: Array, default: () => [] },
});

const submit = inject('search-submit', () => {});
const live = inject('search-live', null);
const skipNextDebounce = inject('search-skip-next-debounce', null);

const areas = computed(() => props.barangays);

// Expose debounce-cancel downward so children (BudgetRangeSlider) can pause
// the pending q/area auto-submit while their popover is open. Otherwise the
// 1000ms ticker fires mid-popover and navigates away from the user.
provide('search-cancel-debounce', () => clearDebounce());

const SEARCH_DEBOUNCE_MS = 1000;
let debounceTimer = null;

function clearDebounce() {
    if (debounceTimer !== null) {
        clearTimeout(debounceTimer);
        debounceTimer = null;
    }
}

function scheduleSubmit() {
    // Parent (Search.vue) flips this when it programmatically rewrites `live`
    // during popstate — without the skip the debounce would re-submit the URL
    // the user just navigated away from.
    if (skipNextDebounce?.value) {
        skipNextDebounce.value = false;
        return;
    }
    clearDebounce();
    debounceTimer = setTimeout(() => {
        debounceTimer = null;
        submit({});
    }, SEARCH_DEBOUNCE_MS);
}

function emitSubmit() {
    clearDebounce();
    submit({});
}

if (live) {
    watch([() => live.q, () => live.budget_min, () => live.budget_max, () => live.area], scheduleSubmit);
}

onBeforeUnmount(clearDebounce);
</script>

<template>
    <div class="bg-white text-gray-900 rounded-2xl p-3 md:p-2 md:pl-4 flex flex-col md:flex-row md:items-center gap-2 shadow-xl">
        <input
            v-if="live"
            v-model="live.q"
            @keydown.enter="emitSubmit"
            type="text"
            placeholder="keywords, amenities (e.g. wifi), 1 bed, 2 baths, 30sqm"
            class="flex-1 bg-transparent outline-none px-2 py-2 text-sm md:text-base placeholder:text-xs md:placeholder:text-sm"
        />
        <div v-if="live" class="flex gap-2 md:contents">
            <BudgetRangeSlider class="flex-1 md:flex-none" />
            <select v-model="live.area" class="flex-1 md:flex-none md:w-44 bg-gray-50 md:bg-transparent rounded-lg px-3 py-2 text-sm border border-gray-200 md:border-0 outline-none">
                <option value="">CDO Areas</option>
                <option v-for="a in areas" :key="a.value" :value="a.value">{{ a.label }}</option>
            </select>
        </div>
        <button
            type="button"
            @click="emitSubmit"
            class="hidden md:inline-flex items-center justify-center bg-orange-500 hover:bg-orange-600 text-white font-medium rounded-xl px-6 py-3 text-sm transition cursor-pointer"
        >
            Search
        </button>
    </div>
</template>
