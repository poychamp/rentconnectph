<script setup>
import { computed } from 'vue';

const props = defineProps({
    listing: { type: Object, required: true },
});

const hasAmenities = computed(() => Array.isArray(props.listing?.amenities) && props.listing.amenities.length > 0);

// Unknown icon names fall back to `default` (tag glyph).
const iconPaths = {
    default: 'M20.59 13.41 12 22l-9-9V3h10l8.59 8.59a2 2 0 0 1 0 2.83ZM7 7h.01',
    droplet: 'M12 2.6S5 11 5 15a7 7 0 0 0 14 0c0-4-7-12.4-7-12.4Z',
    bolt: 'M13 2 3 14h8l-1 8 10-12h-8l1-8Z',
    wifi: 'M5 12.55a11 11 0 0 1 14 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01',
    car: 'M5 17h14M5 17a2 2 0 1 1 4 0M15 17a2 2 0 1 1 4 0M3 13h18l-2-6H5l-2 6Z',
    sofa: 'M3 11V7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4M3 11h18M3 11v6h18v-6M5 17v3M19 17v3',
    parking: 'M5 17h14M5 17a2 2 0 1 1 4 0M15 17a2 2 0 1 1 4 0M3 13h18l-2-6H5l-2 6Z',
    cigarette: 'M3 14h12v3H3z M16 14h4v3h-4z M5 10v-3 M9 10v-3 M13 10v-3',
    smoking: 'M3 14h12v3H3z M16 14h4v3h-4z M5 10v-3 M9 10v-3 M13 10v-3',
    no_smoking: 'M3 14h12v3H3z M16 14h4v3h-4z M5 10v-3 M9 10v-3 M13 10v-3 M2 18l20 -8',
    pets: 'M12 14 m-3 0 a3 3 0 1 0 6 0 a3 3 0 1 0 -6 0 M6 9 m-1.5 0 a1.5 1.5 0 1 0 3 0 a1.5 1.5 0 1 0 -3 0 M18 9 m-1.5 0 a1.5 1.5 0 1 0 3 0 a1.5 1.5 0 1 0 -3 0 M10 5 m-1 0 a1 1 0 1 0 2 0 a1 1 0 1 0 -2 0 M14 5 m-1 0 a1 1 0 1 0 2 0 a1 1 0 1 0 -2 0',
    no_pets: 'M12 14 m-3 0 a3 3 0 1 0 6 0 a3 3 0 1 0 -6 0 M6 9 m-1.5 0 a1.5 1.5 0 1 0 3 0 a1.5 1.5 0 1 0 -3 0 M18 9 m-1.5 0 a1.5 1.5 0 1 0 3 0 a1.5 1.5 0 1 0 -3 0 M10 5 m-1 0 a1 1 0 1 0 2 0 a1 1 0 1 0 -2 0 M14 5 m-1 0 a1 1 0 1 0 2 0 a1 1 0 1 0 -2 0 M2 22 l20 -20',
    aircon: 'M3 6h18v8H3z M3 11h18 M7 17v3 M12 17v3 M17 17v3',
};
</script>

<template>
    <section v-if="hasAmenities">
        <h2 class="text-xl md:text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
            Amenities
        </h2>
        <ul class="mt-4 flex flex-wrap gap-2">
            <li
                v-for="amenity in listing.amenities"
                :key="amenity.id"
                class="inline-flex items-center gap-2 border border-gray-200 dark:border-gray-700 rounded-full px-3 py-1.5 text-sm text-gray-700 dark:text-gray-200"
            >
                <svg
                    class="w-4 h-4 text-orange-500"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path :d="iconPaths[amenity.icon] || iconPaths.default" />
                </svg>
                {{ amenity.name }}
            </li>
        </ul>
    </section>
</template>
