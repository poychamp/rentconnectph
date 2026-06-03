<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, inject, toRef } from 'vue';
import mapboxgl from 'mapbox-gl';
import 'mapbox-gl/dist/mapbox-gl.css';

const CDO_LAT = 8.4542;
const CDO_LNG = 124.6411;

const form = inject('addListingForm');
const errors = inject('addListingFormErrors', ref({}));
const { validateField, clearFieldError } = inject('addListingFormValidate', {
    validateField: () => true,
    clearFieldError: () => {},
});
const lat = toRef(form, 'latitude');
const lng = toRef(form, 'longitude');

const latError = computed(() => errors.value?.latitude?.[0] ?? null);
const lngError = computed(() => errors.value?.longitude?.[0] ?? null);

const geoError = ref(null);

const mapEl = ref(null);
let map = null;
let marker = null;

const token = window.__ASSETS__?.mapboxToken;

function isDark() {
    return document.documentElement.classList.contains('dark');
}

function styleUrl() {
    return isDark()
        ? 'mapbox://styles/mapbox/dark-v11'
        : 'mapbox://styles/mapbox/streets-v12';
}

function placePin(latitude, longitude) {
    if (!map) return;
    if (marker) {
        marker.setLngLat([longitude, latitude]);
    } else {
        marker = new mapboxgl.Marker({ color: '#f97316', draggable: true })
            .setLngLat([longitude, latitude])
            .addTo(map);
        marker.on('dragend', () => {
            const ll = marker.getLngLat();
            lat.value = parseFloat(ll.lat.toFixed(7));
            lng.value = parseFloat(ll.lng.toFixed(7));
        });
    }
    lat.value = parseFloat(latitude.toFixed(7));
    lng.value = parseFloat(longitude.toFixed(7));
}

function useMyLocation() {
    geoError.value = null;

    if (!navigator.geolocation) {
        geoError.value = 'Your browser does not support geolocation.';
        return;
    }

    if (!window.isSecureContext) {
        geoError.value = 'Geolocation requires HTTPS (or http://localhost). This origin is insecure.';
        return;
    }

    navigator.geolocation.getCurrentPosition(
        (pos) => {
            placePin(pos.coords.latitude, pos.coords.longitude);
            map?.flyTo({ center: [pos.coords.longitude, pos.coords.latitude], zoom: 15 });
        },
        (err) => {
            const reasons = {
                1: 'Permission denied. Allow location access in your browser.',
                2: 'Location unavailable.',
                3: 'Location request timed out.',
            };
            geoError.value = reasons[err.code] ?? `Geolocation failed: ${err.message}`;
        },
        { enableHighAccuracy: true, timeout: 10000 },
    );
}

watch([lat, lng], ([newLat, newLng]) => {
    if (newLat == null || newLng == null) return;
    if (!map) return;
    try {
        if (!marker) {
            marker = new mapboxgl.Marker({ color: '#f97316', draggable: true })
                .setLngLat([newLng, newLat])
                .addTo(map);
            marker.on('dragend', () => {
                const ll = marker.getLngLat();
                lat.value = parseFloat(ll.lat.toFixed(7));
                lng.value = parseFloat(ll.lng.toFixed(7));
            });
        } else {
            marker.setLngLat([newLng, newLat]);
        }
    } catch (err) {
        console.warn('Mapbox setLngLat rejected coordinates:', { lat: newLat, lng: newLng, err });
    }
});

onMounted(() => {
    if (!token || !mapEl.value) return;

    mapboxgl.accessToken = token;

    map = new mapboxgl.Map({
        container: mapEl.value,
        style: styleUrl(),
        center: [CDO_LNG, CDO_LAT],
        zoom: 12,
        attributionControl: false,
    });

    map.addControl(new mapboxgl.NavigationControl({ showCompass: false }), 'top-right');
    map.addControl(new mapboxgl.AttributionControl({ compact: true }), 'bottom-right');

    map.on('click', (e) => {
        placePin(e.lngLat.lat, e.lngLat.lng);
    });

    if (lat.value != null && lng.value != null) {
        placePin(lat.value, lng.value);
        map.jumpTo({ center: [lng.value, lat.value], zoom: 15 });
    }

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
    <div>
        <div class="flex items-baseline justify-between">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Pin location
            </label>
            <span class="text-xs text-gray-400 dark:text-gray-500">
                optional · click map or type coords
            </span>
        </div>

        <div class="mt-1 grid grid-cols-2 gap-2">
            <input
                v-model.number="lat"
                type="number"
                step="any"
                placeholder="Latitude (e.g. 8.4542)"
                @focus="clearFieldError('latitude')"
                @blur="validateField('latitude')"
                :class="[
                    'w-full rounded-md border bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:ring-1 outline-none transition',
                    latError
                        ? 'border-red-400 dark:border-red-500 focus:border-red-500 focus:ring-red-500'
                        : 'border-gray-200 dark:border-gray-700 focus:border-orange-500 focus:ring-orange-500',
                ]"
            >
            <input
                v-model.number="lng"
                type="number"
                step="any"
                placeholder="Longitude (e.g. 124.6411)"
                @focus="clearFieldError('longitude')"
                @blur="validateField('longitude')"
                :class="[
                    'w-full rounded-md border bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:ring-1 outline-none transition',
                    lngError
                        ? 'border-red-400 dark:border-red-500 focus:border-red-500 focus:ring-red-500'
                        : 'border-gray-200 dark:border-gray-700 focus:border-orange-500 focus:ring-orange-500',
                ]"
            >
        </div>
        <p v-if="latError" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ latError }}</p>
        <p v-if="lngError" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ lngError }}</p>

        <div class="mt-2 rounded-md overflow-hidden border border-gray-200 dark:border-gray-700">
            <div
                v-if="token"
                ref="mapEl"
                class="w-full h-52 bg-gray-100 dark:bg-gray-800"
            ></div>
            <div
                v-else
                class="w-full h-52 flex items-center justify-center bg-gray-50 dark:bg-gray-900 text-sm text-gray-400 dark:text-gray-500"
            >
                Map unavailable (Mapbox token missing)
            </div>
        </div>

        <p v-if="geoError" class="mt-2 text-xs text-red-600 dark:text-red-400">
            {{ geoError }}
        </p>

        <div class="mt-2 flex items-center justify-between text-xs">
            <p class="text-gray-500 dark:text-gray-400">Click on the map to drop a pin.</p>
            <button
                type="button"
                @click="useMyLocation"
                class="inline-flex items-center gap-1 rounded-md border border-gray-200 dark:border-gray-700 px-2.5 py-1 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
            >
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="3"/>
                    <path d="M12 2v2M12 20v2M2 12h2M20 12h2"/>
                </svg>
                Use my location
            </button>
        </div>
    </div>
</template>
