<script setup>
import { onMounted, onBeforeUnmount, ref, computed } from 'vue';
import mapboxgl from 'mapbox-gl';
import 'mapbox-gl/dist/mapbox-gl.css';

const props = defineProps({
    listing: { type: Object, required: true },
});

const mapEl = ref(null);
let map = null;

const token = window.__ASSETS__?.mapboxToken;

const hasCoords = computed(
    () => props.listing.latitude != null && props.listing.longitude != null
);

function isDark() {
    return document.documentElement.classList.contains('dark');
}

function styleUrl() {
    return isDark()
        ? 'mapbox://styles/mapbox/dark-v11'
        : 'mapbox://styles/mapbox/streets-v12';
}

onMounted(() => {
    if (!token || !mapEl.value || !hasCoords.value) return;

    mapboxgl.accessToken = token;

    map = new mapboxgl.Map({
        container: mapEl.value,
        style: styleUrl(),
        center: [props.listing.longitude, props.listing.latitude],
        zoom: 15,
        attributionControl: false,
    });

    map.addControl(new mapboxgl.NavigationControl({ showCompass: false }), 'top-right');
    map.addControl(new mapboxgl.AttributionControl({ compact: true }), 'bottom-right');

    new mapboxgl.Marker({ color: '#f97316' })
        .setLngLat([props.listing.longitude, props.listing.latitude])
        .addTo(map);

    const observer = new MutationObserver(() => {
        if (map) map.setStyle(styleUrl());
    });
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });
    map._themeObserver = observer;
});

onBeforeUnmount(() => {
    if (map?._themeObserver) map._themeObserver.disconnect();
    map?.remove();
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
                    v-if="token"
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
