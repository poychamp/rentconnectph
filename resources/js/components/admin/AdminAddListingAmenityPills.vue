<script setup>
import { ref, computed, inject } from 'vue';

const props = defineProps({
    amenities: { type: Array, required: true },
    modelValue: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:modelValue']);

// Local selection state, mirrors v-model
const selected = ref([...props.modelValue]);

const activeCount = computed(() => props.amenities.length);

const errors = inject('addListingFormErrors', ref({}));
// Pick up either bag-level errors (e.g. "amenities must be an array") or per-row
// errors from validation like "amenities.0" — show whichever message comes first.
const fieldError = computed(() => {
    const bag = errors.value ?? {};
    if (bag.amenities?.[0]) return bag.amenities[0];
    const rowKey = Object.keys(bag).find(k => k.startsWith('amenities.'));
    return rowKey ? bag[rowKey][0] : null;
});

// Duplicate of the public ListingDetailAmenities iconPaths map (no cross-domain import per CLAUDE.md)
const iconPaths = {
    droplet: 'M12 2.6S5 11 5 15a7 7 0 0 0 14 0c0-4-7-12.4-7-12.4Z',
    bolt:    'M13 2 3 14h8l-1 8 10-12h-8l1-8Z',
    wifi:    'M5 12.55a11 11 0 0 1 14 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01',
    car:     'M5 17h14M5 17a2 2 0 1 1 4 0M15 17a2 2 0 1 1 4 0M3 13h18l-2-6H5l-2 6Z',
    sofa:    'M3 11V7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4M3 11h18M3 11v6h18v-6M5 17v3M19 17v3',
};

function isSelected(id) {
    return selected.value.includes(id);
}

function toggle(id) {
    const idx = selected.value.indexOf(id);
    if (idx === -1) {
        selected.value.push(id);
    } else {
        selected.value.splice(idx, 1);
    }
    emit('update:modelValue', [...selected.value]);
}
</script>

<template>
    <div>
        <div class="flex items-baseline justify-between">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Amenities
            </label>
            <span class="text-xs text-gray-400 dark:text-gray-500">
                {{ activeCount }} active ·
                <a href="#" class="text-orange-500 hover:text-orange-600">manage in Amenities</a>
            </span>
        </div>

        <ul
            :class="[
                'mt-2 flex flex-wrap gap-2',
                fieldError ? 'rounded-md border border-red-400 dark:border-red-500 p-2' : '',
            ]"
        >
            <li v-for="amenity in amenities" :key="amenity.id">
                <button
                    type="button"
                    @click="toggle(amenity.id)"
                    :class="[
                        'inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm border transition cursor-pointer',
                        isSelected(amenity.id)
                            ? 'border-orange-500 bg-orange-50 text-orange-700 dark:bg-orange-950/40 dark:text-orange-300'
                            : 'border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800'
                    ]"
                >
                    <svg
                        v-if="amenity.icon && iconPaths[amenity.icon]"
                        class="w-4 h-4"
                        :class="isSelected(amenity.id) ? 'text-orange-500' : 'text-gray-400 dark:text-gray-500'"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path :d="iconPaths[amenity.icon]" />
                    </svg>
                    {{ amenity.name }}
                </button>
            </li>
        </ul>

        <p v-if="fieldError" class="mt-1 text-xs text-red-600 dark:text-red-400">
            {{ fieldError }}
        </p>
    </div>
</template>
