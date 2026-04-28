<script setup>
import { computed, inject } from 'vue';

const props = defineProps({
    barangays:    { type: Array, default: () => [] },
    listingTypes: { type: Array, default: () => [] },
});

const submit = inject('search-submit', () => {});
const live = inject('search-live', null);

const budgets = [
    { value: 'lt10k',  label: '< ₱10,000' },
    { value: '10-20k', label: '₱10,000 – ₱20,000' },
    { value: '20-30k', label: '₱20,000 – ₱30,000' },
    { value: 'gt30k',  label: '> ₱30,000' },
];

const areas = computed(() => props.barangays);

function emitSubmit() {
    // Live state already current via v-model — pass empty patch.
    submit({});
}
</script>

<template>
    <div class="bg-white text-gray-900 rounded-2xl p-3 md:p-2 md:pl-4 flex flex-col md:flex-row md:items-center gap-2 shadow-xl">
        <input
            v-if="live"
            v-model="live.q"
            @keydown.enter="emitSubmit"
            type="text"
            placeholder="Search by keywords, beds, baths, amenities, sqm..."
            class="flex-1 bg-transparent outline-none px-2 py-2 text-sm md:text-base placeholder:text-xs md:placeholder:text-sm"
        />
        <div v-if="live" class="flex gap-2 md:contents">
            <select v-model="live.budget" class="flex-1 md:flex-none md:w-40 bg-gray-50 md:bg-transparent rounded-lg px-3 py-2 text-sm border border-gray-200 md:border-0 outline-none">
                <option value="">Budget</option>
                <option v-for="b in budgets" :key="b.value" :value="b.value">{{ b.label }}</option>
            </select>
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
