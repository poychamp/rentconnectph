<script setup>
import { onMounted, onBeforeUnmount, ref, computed } from 'vue';

const props = defineProps({
    listing: { type: Object, required: true },
});

const mapEl = ref(null);
let map = null;

const googleMapsEnabled = window.__ASSETS__?.googleMapsEnabled === true;

const hasCoords = computed(
    () => props.listing.latitude != null && props.listing.longitude != null
);

onMounted(async () => {
    if (!googleMapsEnabled || !mapEl.value || !hasCoords.value) return;
    if (!window.google?.maps?.importLibrary) return;

    const { Map } = await google.maps.importLibrary('maps');
    const position = {
        lat: Number(props.listing.latitude),
        lng: Number(props.listing.longitude),
    };

    map = new Map(mapEl.value, {
        center: position,
        zoom: 15,
        disableDefaultUI: true,
        zoomControl: true,
        gestureHandling: 'cooperative',
        clickableIcons: false,
    });

    new google.maps.Marker({
        position,
        map,
        icon: {
            path: google.maps.SymbolPath.CIRCLE,
            fillColor: '#f97316',
            fillOpacity: 1,
            strokeColor: '#ffffff',
            strokeWeight: 2,
            scale: 10,
        },
    });
});

onBeforeUnmount(() => {
    map = null;
});
</script>

<template>
    <section>
        <h2 class="text-xl md:text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
            Location
        </h2>

        <template v-if="hasCoords">
            <div class="mt-3 rounded-2xl overflow-hidden border border-gray-100 dark:border-gray-800">
                <div
                    v-if="googleMapsEnabled"
                    ref="mapEl"
                    class="w-full h-72 bg-gray-100 dark:bg-gray-800"
                ></div>
                <img
                    v-else
                    :src="listing.map_url"
                    :alt="`Map of ${listing.barangay_label}, Cagayan de Oro`"
                    class="w-full h-72 object-cover bg-gray-100 dark:bg-gray-800"
                />
            </div>
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                Exact address provided after inquiry. Map shows approximate barangay-level location.
            </p>
            <a
                :href="`https://www.google.com/maps/search/?api=1&query=${listing.latitude},${listing.longitude}`"
                target="_blank"
                rel="noopener noreferrer"
                class="mt-4 inline-flex items-center justify-center gap-2 w-full sm:w-auto px-5 py-2.5 rounded-xl border border-orange-500 bg-transparent text-orange-500 text-sm font-semibold transition-opacity duration-[80ms] ease-out active:opacity-50"
            >
                <svg
                    class="w-4 h-4"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                    <circle cx="12" cy="10" r="3" />
                </svg>
                Open in Google Maps
                <svg
                    class="w-3.5 h-3.5 opacity-80"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M15 3h6v6" />
                    <path d="M10 14L21 3" />
                    <path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5" />
                </svg>
            </a>
        </template>

        <div
            v-else
            class="mt-3 rounded-2xl border border-dashed border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 px-6 py-12 text-center"
        >
            <svg
                class="w-8 h-8 mx-auto text-gray-400 dark:text-gray-500"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.75"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path d="M3 3l18 18" />
                <path d="M19.5 13c.6-1.4 1-2.8 1-4 0-3.9-3.1-7-7-7-1.4 0-2.7.4-3.8 1.1" />
                <path d="M11.4 6.4a3 3 0 1 0 4.2 4.2" />
                <path d="M9 12c0 5.25 3 9 3 9s2-2.5 2.7-5.6" />
            </svg>
            <p class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                No coordinates set for this listing
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Map preview will appear once the broker provides location data.
            </p>
        </div>
    </section>
</template>
