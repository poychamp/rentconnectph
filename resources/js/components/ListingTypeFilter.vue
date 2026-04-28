<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';

defineProps({
    listingTypes: { type: Array, default: () => [] },
});

const activeSet = ref(new Set());
const allActive = computed(() => activeSet.value.size === 0);

function isActive(value) {
    return activeSet.value.has(value);
}

let debounceTimer = null;
const DEBOUNCE_MS = 1000;

function scheduleNavigate() {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        const types = [...activeSet.value];
        const qs = types.length > 0
            ? `?type=${types.map(encodeURIComponent).join(',')}`
            : '';
        window.location.assign(`/search${qs}`);
    }, DEBOUNCE_MS);
}

function togglePick(value) {
    const next = new Set(activeSet.value);
    if (next.has(value)) {
        next.delete(value);
    } else {
        next.add(value);
    }
    activeSet.value = next;
    scheduleNavigate();
}

function clearAll() {
    if (allActive.value) return;
    activeSet.value = new Set();
    scheduleNavigate();
}

onBeforeUnmount(() => {
    if (debounceTimer) clearTimeout(debounceTimer);
});
</script>

<template>
    <div class="border-b border-gray-100 bg-white dark:bg-gray-900 dark:border-gray-800">
        <div class="max-w-7xl mx-auto px-4 md:px-6 lg:px-8">
            <div class="flex flex-wrap gap-2 py-4">
                <button
                    type="button"
                    @click="clearAll"
                    :class="[
                        'px-4 py-2 rounded-full text-sm font-medium transition border cursor-pointer',
                        allActive
                            ? 'bg-orange-500 text-white border-orange-500'
                            : 'bg-white text-gray-700 border-gray-200 hover:border-gray-300 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700 dark:hover:border-gray-600',
                    ]"
                >
                    All
                </button>
                <button
                    v-for="type in listingTypes"
                    :key="type.value"
                    type="button"
                    @click="togglePick(type.value)"
                    :class="[
                        'px-4 py-2 rounded-full text-sm font-medium transition border cursor-pointer',
                        isActive(type.value)
                            ? 'bg-orange-500 text-white border-orange-500'
                            : 'bg-white text-gray-700 border-gray-200 hover:border-gray-300 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700 dark:hover:border-gray-600',
                    ]"
                >
                    {{ type.label === 'Apartment' ? 'Apartments' : type.label }}
                </button>
            </div>
        </div>
    </div>
</template>
