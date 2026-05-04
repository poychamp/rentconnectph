<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    initialQ: { type: String, default: '' },
});

const q = ref(props.initialQ);

let timer = null;

function navigate() {
    const params = new URLSearchParams();
    if (q.value.trim() !== '') params.set('q', q.value.trim());
    const qs = params.toString();
    window.location.assign(qs ? `/admin/inquiries?${qs}` : '/admin/inquiries');
}

function scheduleNavigate() {
    if (timer) clearTimeout(timer);
    timer = setTimeout(navigate, 1000);
}

function instantNavigate() {
    if (timer) clearTimeout(timer);
    navigate();
}

function onSearchEnter(e) {
    e.preventDefault();
    instantNavigate();
}

function reset() {
    q.value = '';
    instantNavigate();
}

watch(q, () => scheduleNavigate());

onBeforeUnmount(() => { if (timer) clearTimeout(timer); });
</script>

<template>
    <div class="mb-4 flex flex-col sm:flex-row gap-3">
        <div class="flex-1 relative">
            <input
                type="search"
                v-model="q"
                placeholder="Search by renter name, phone, or listing title…"
                @keydown.enter="onSearchEnter"
                class="block w-full rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-white pl-10 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
            >
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="7" />
                <path d="m21 21-4.3-4.3" />
            </svg>
        </div>
        <button
            v-if="q"
            type="button"
            @click="reset"
            class="px-3 py-2 text-sm font-medium rounded-md border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
        >
            Reset
        </button>
    </div>
</template>
