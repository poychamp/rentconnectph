<script setup>
import { computed } from 'vue';

const props = defineProps({
    currentPage: { type: Number, required: true },
    totalPages:  { type: Number, required: true },
    totalRows:   { type: Number, required: true },
    perPage:     { type: Number, required: true },
});

const emit = defineEmits(['change']);

const startRow = computed(() => props.totalRows === 0 ? 0 : (props.currentPage - 1) * props.perPage + 1);
const endRow   = computed(() => Math.min(props.currentPage * props.perPage, props.totalRows));

const pageItems = computed(() => {
    const total   = props.totalPages;
    const current = props.currentPage;

    if (total <= 7) {
        return Array.from({ length: total }, (_, i) => i + 1);
    }

    const wanted = new Set([1, total, current, current - 1, current + 1]);
    if (current <= 3) { wanted.add(2); wanted.add(3); }
    if (current >= total - 2) { wanted.add(total - 1); wanted.add(total - 2); }

    const sorted = Array.from(wanted).filter(p => p >= 1 && p <= total).sort((a, b) => a - b);

    const result = [];
    let prev = 0;
    for (const p of sorted) {
        if (p - prev > 1) result.push('…');
        result.push(p);
        prev = p;
    }
    return result;
});

function go(page) {
    if (typeof page !== 'number') return;
    if (page < 1 || page > props.totalPages || page === props.currentPage) return;
    emit('change', page);
}
</script>

<template>
    <div v-if="totalRows > 0" class="flex items-center justify-between mt-4">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Showing {{ startRow }}–{{ endRow }} of {{ totalRows }}
        </p>

        <div class="flex items-center gap-1">
            <button type="button" @click="go(currentPage - 1)" :disabled="currentPage === 1"
                class="inline-flex items-center px-2.5 py-1.5 rounded-md text-xs font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer">
                <svg class="w-3.5 h-3.5 mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6" />
                </svg>
                Prev
            </button>

            <template v-for="(item, idx) in pageItems" :key="idx">
                <span v-if="item === '…'" class="inline-flex items-center justify-center w-8 h-8 text-xs text-gray-400 dark:text-gray-500 select-none">…</span>
                <button v-else type="button" @click="go(item)"
                    :class="[
                        'inline-flex items-center justify-center w-8 h-8 rounded-md text-xs font-semibold cursor-pointer transition',
                        item === currentPage
                            ? 'bg-slate-900 text-white dark:bg-orange-500'
                            : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800',
                    ]">
                    {{ item }}
                </button>
            </template>

            <button type="button" @click="go(currentPage + 1)" :disabled="currentPage === totalPages"
                class="inline-flex items-center px-2.5 py-1.5 rounded-md text-xs font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer">
                Next
                <svg class="w-3.5 h-3.5 ml-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6" />
                </svg>
            </button>
        </div>
    </div>
</template>
