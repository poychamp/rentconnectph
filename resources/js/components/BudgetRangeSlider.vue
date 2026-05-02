<script setup>
import { computed, inject, ref, watch } from 'vue';
import Slider from 'primevue/slider';

const props = defineProps({
    min:  { type: Number, default: 0 },
    max:  { type: Number, default: 100000 },
    step: { type: Number, default: 1000 },
});

// Sensible default for the upper handle when no budget_max has been committed —
// most CDO rentals fall under ₱20k, so opening the modal pre-positions the
// upper handle there. User can drag higher up to props.max (₱100k) to widen.
const DEFAULT_UPPER_HANDLE = 20000;

const live           = inject('search-live', null);
const submit         = inject('search-submit', () => {});
const cancelDebounce = inject('search-cancel-debounce', () => {});

const localMin = ref(live?.budget_min ?? null);
const localMax = ref(live?.budget_max ?? DEFAULT_UPPER_HANDLE);

// Plain ref array bound to PrimeVue Slider — avoids computed-getter/setter
// fighting the drag emit cycle. We sync localMin/localMax from this via watch,
// and seed it from localMin/localMax when those change externally (e.g. Clear).
const sliderValue = ref([
    localMin.value ?? props.min,
    localMax.value ?? props.max,
]);

// Slider drag → update localMin/localMax (null when at the extremes).
watch(sliderValue, ([a, b]) => {
    localMin.value = a === props.min ? null : a;
    localMax.value = b === props.max ? null : b;
}, { deep: true });

// External clear / type-input → keep slider handles in sync with localMin/Max.
watch([localMin, localMax], ([min, max]) => {
    const next = [min ?? props.min, max ?? props.max];
    if (sliderValue.value[0] !== next[0] || sliderValue.value[1] !== next[1]) {
        sliderValue.value = next;
    }
});

const showPanel = ref(false);

const triggerLabel = computed(() => {
    // Read committed values from `live` (NOT local). The 20k default for the
    // slider handle is just visual positioning inside the popover — until the
    // user commits via close, the trigger should still show "Budget" (no filter).
    const min = live?.budget_min ?? null;
    const max = live?.budget_max ?? null;
    const fmt = (n) => `₱${(n / 1000).toLocaleString('en-US', { maximumFractionDigits: 1 })}k`;
    if (min === null && max === null) return 'Budget';
    if (min === null) return `≤ ${fmt(max)}`;
    if (max === null) return `≥ ${fmt(min)}`;
    return `${fmt(min)} – ${fmt(max)}`;
});

function clearAll() {
    localMin.value = null;
    localMax.value = null;
}

function togglePanel() {
    if (!showPanel.value) {
        // Opening: cancel any pending parent debounce so q/area changes don't
        // auto-submit and navigate away while the user's interacting with budget.
        cancelDebounce();
    }
    showPanel.value = !showPanel.value;
}

function closePanel() {
    // Every close path (Done, X, backdrop click) stages local values onto the
    // shared `live` reactive — the parent's debounced watcher on
    // [live.q, live.budget_min, live.budget_max, live.area] picks up the change
    // and schedules navigation 1000ms later. If the user keeps typing in q or
    // changing area before then, the debounce restarts. Same UX as typing in q:
    // stage → debounce → navigate.
    if (live) {
        live.budget_min = localMin.value;
        live.budget_max = localMax.value;
    }
    showPanel.value = false;
}
</script>

<template>
    <div class="relative">
        <button
            type="button"
            @click="togglePanel"
            :aria-haspopup="'dialog'"
            :aria-expanded="showPanel"
            class="w-full md:w-auto md:min-w-[14rem] flex items-center justify-between gap-2 bg-gray-50 md:bg-transparent rounded-lg px-4 py-2 md:py-3 text-sm md:text-base border border-gray-200 md:border-0 outline-none cursor-pointer min-h-[44px]"
        >
            <span class="truncate">{{ triggerLabel }}</span>
            <svg class="w-3.5 h-3.5 opacity-60 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="m6 9 6 6 6-6"/>
            </svg>
        </button>

        <Teleport to="body">
            <div
                v-if="showPanel"
                class="fixed inset-0 z-50 flex items-end md:items-start md:pt-24 justify-center"
                role="dialog"
                aria-modal="true"
                aria-label="Budget filter"
            >
                <div
                    class="absolute inset-0 bg-black/40"
                    @click="closePanel"
                ></div>

                <div
                    class="relative w-full md:w-[28rem] bg-white dark:bg-gray-900 rounded-t-2xl md:rounded-2xl shadow-2xl border-t md:border md:border-gray-200 dark:border-gray-800 max-h-[80vh] overflow-y-auto"
                >
                    <div class="flex items-center justify-between px-5 pt-4 pb-3">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Budget</h3>
                        <button
                            type="button"
                            @click="closePanel"
                            aria-label="Close"
                            class="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 cursor-pointer p-1 -m-1"
                        >
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 6 6 18M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="px-6 py-6 budget-slider-track">
                        <Slider
                            v-model="sliderValue"
                            range
                            :min="props.min"
                            :max="props.max"
                            :step="props.step"
                            class="w-full"
                        />
                    </div>

                    <div class="px-5 grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Min</label>
                            <div class="relative">
                                <input
                                    type="text"
                                    :value="localMin ?? ''"
                                    readonly
                                    placeholder="0"
                                    aria-label="Minimum budget"
                                    class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100 rounded-md px-3 pr-14 py-2 text-sm outline-none cursor-default tabular-nums"
                                >
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-gray-400 dark:text-gray-500 pointer-events-none">PHP</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Max</label>
                            <div class="relative">
                                <input
                                    type="text"
                                    :value="localMax ?? ''"
                                    readonly
                                    placeholder="No limit"
                                    aria-label="Maximum budget"
                                    class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100 rounded-md px-3 pr-14 py-2 text-sm outline-none cursor-default tabular-nums"
                                >
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-gray-400 dark:text-gray-500 pointer-events-none">PHP</span>
                            </div>
                        </div>
                    </div>

                    <div class="px-5 pt-5 pb-5 flex items-center justify-between gap-3">
                        <button
                            type="button"
                            @click="clearAll"
                            class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-200 cursor-pointer min-h-[44px] px-2"
                        >
                            Clear
                        </button>
                        <button
                            type="button"
                            @click="closePanel"
                            class="px-5 py-2 rounded-md bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold cursor-pointer min-h-[44px]"
                        >
                            Done
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.budget-slider-track :deep(.p-slider) {
    position: relative;
    display: block;
    height: 6px;
    background: rgb(229 231 235);
    border-radius: 9999px;
    cursor: pointer;
}

.dark .budget-slider-track :deep(.p-slider) {
    background: rgb(55 65 81);
}

.budget-slider-track :deep(.p-slider-range) {
    position: absolute;
    height: 100%;
    background: rgb(249 115 22);
    border-radius: 9999px;
}

.budget-slider-track :deep(.p-slider-handle) {
    position: absolute;
    top: 50%;
    width: 24px;
    height: 24px;
    background: rgb(249 115 22);
    border: 2px solid white;
    border-radius: 9999px;
    margin-top: -12px;
    margin-left: -12px;
    cursor: grab;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
    transition: transform 0.1s ease, box-shadow 0.1s ease;
    touch-action: none;
}

.budget-slider-track :deep(.p-slider-handle:hover) {
    transform: scale(1.1);
    box-shadow: 0 4px 10px rgba(249, 115, 22, 0.4);
}

.budget-slider-track :deep(.p-slider-handle:active),
.budget-slider-track :deep(.p-slider-handle[data-p-sliding="true"]) {
    cursor: grabbing;
    transform: scale(1.15);
}

.dark .budget-slider-track :deep(.p-slider-handle) {
    border-color: rgb(17 24 39);
}
</style>

