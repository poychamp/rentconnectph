<script setup>
import { ref, reactive, provide, computed, onMounted, nextTick } from 'vue';
import AdminSidebar from './components/admin/AdminSidebar.vue';
import AdminTopBar from './components/admin/AdminTopBar.vue';
import AdminAddListingShortcutPill from './components/admin/AdminAddListingShortcutPill.vue';
import AdminAddListingFormCard from './components/admin/AdminAddListingFormCard.vue';
import AdminAddListingPublishBar from './components/admin/AdminAddListingPublishBar.vue';

// Read initial state synchronously at setup so children inherit a fully-populated
// form on first render. If we deferred this to onMounted, child components
// (MapPicker especially) would mount with null lat/lng and a later mutation
// would trigger their watchers — which, for out-of-range lat/lng, can crash
// Mapbox's setLngLat and drop the same-tick input value update.
const initial = window.__INITIAL_ADD_LISTING__ ?? null;
const old = initial?.oldInput ?? null;

const numOrNull = (v) => v === undefined || v === null || v === '' ? null : Number(v);
const numOr = (v, fallback) => v === undefined || v === null || v === '' ? fallback : (Number(v) || fallback);

const user = ref(window.__INITIAL_DASHBOARD__?.user ?? {
    name:       'Admin',
    initials:   'AD',
    role_label: 'Super Admin',
});

const formData = ref({
    listingTypes: initial?.listingTypes ?? [],
    barangays:    initial?.barangays    ?? [],
    amenities:    initial?.amenities    ?? [],
});

// Shared submit-payload state. Each child reads/writes its slice via inject('addListingForm').
// Pre-populated from `old()` when Laravel redirected back after a validation failure.
const addListingForm = reactive({
    title:        old?.title        ?? '',
    description:  old?.description  ?? '',
    listing_type: old?.listing_type ?? '',
    monthly_rent: numOrNull(old?.monthly_rent),
    barangay:     old?.barangay ?? '',
    beds:         numOr(old?.beds, 0),
    baths:        numOr(old?.baths, 0),
    sqft:         numOr(old?.sqft, 0),
    latitude:     numOrNull(old?.latitude),
    longitude:    numOrNull(old?.longitude),
    amenities:    Array.isArray(old?.amenities) ? old.amenities.map(Number) : [],
    photos:       Array.isArray(old?.photos)    ? old.photos                : [],
    is_featured:  old?.is_featured === '1' || old?.is_featured === true,
});

// Per-field validation errors, keyed by Laravel field name (e.g. 'title', 'photos.0.key').
// Each value is an array of message strings — children render the first one.
const addListingFormErrors = ref(initial?.errors ?? {});

const errorCount = computed(() => Object.keys(addListingFormErrors.value).length);

// ---------- Client-side validation ----------
//
// Mirror of the controller's rules so we can short-circuit a submission that's
// guaranteed to fail server-side. The server still re-runs everything; this is
// purely UX (instant feedback on blur, no round-trip).
//
// Pattern per field: validator returns either an error message string OR null
// when the value is acceptable. setFieldError reassigns the entire errors
// object (instead of mutating in place) so Vue's ref reactivity fires.

const VALIDATORS = {
    title: (f) => {
        const v = (f.title ?? '').trim();
        if (!v) return 'Title is required.';
        if (v.length > 200) return 'Title is too long (max 200 characters).';
        return null;
    },
    listing_type: (f) => f.listing_type ? null : 'Listing type is required.',
    monthly_rent: (f) => {
        if (f.monthly_rent === null || f.monthly_rent === '' || f.monthly_rent === undefined) {
            return 'Monthly rent is required.';
        }
        const n = Number(f.monthly_rent);
        if (!Number.isFinite(n) || n < 1) return 'Monthly rent must be at least ₱1.';
        return null;
    },
    barangay: (f) => f.barangay ? null : 'Barangay is required.',
    beds: (f) => {
        const n = Number(f.beds);
        if (!Number.isFinite(n)) return 'Bedrooms is required.';
        if (n < 0 || n > 20) return 'Bedrooms must be between 0 and 20.';
        return null;
    },
    baths: (f) => {
        const n = Number(f.baths);
        if (!Number.isFinite(n)) return 'Bathrooms is required.';
        if (n < 0 || n > 20) return 'Bathrooms must be between 0 and 20.';
        return null;
    },
    sqft: (f) => {
        const n = Number(f.sqft);
        if (!Number.isFinite(n) || n < 1) return 'Floor area must be at least 1 sqft.';
        return null;
    },
    latitude: (f) => {
        if (f.latitude === null || f.latitude === '' || f.latitude === undefined) return null;
        const n = Number(f.latitude);
        if (!Number.isFinite(n)) return 'Latitude must be a number.';
        if (n < -90 || n > 90) return 'Latitude must be between -90 and 90.';
        return null;
    },
    longitude: (f) => {
        if (f.longitude === null || f.longitude === '' || f.longitude === undefined) return null;
        const n = Number(f.longitude);
        if (!Number.isFinite(n)) return 'Longitude must be a number.';
        if (n < -180 || n > 180) return 'Longitude must be between -180 and 180.';
        return null;
    },
    photos: (f) => {
        if (!Array.isArray(f.photos) || f.photos.length === 0) return 'At least one photo is required.';
        if (f.photos.length > 20) return 'Maximum 20 photos allowed.';
        return null;
    },
};

function setFieldError(field, message) {
    const next = { ...addListingFormErrors.value };
    if (message) next[field] = [message];
    else delete next[field];
    addListingFormErrors.value = next;
}

function validateField(field) {
    const fn = VALIDATORS[field];
    if (!fn) return true;
    const message = fn(addListingForm);
    setFieldError(field, message);
    return !message;
}

function clearFieldError(field) {
    if (addListingFormErrors.value[field]) setFieldError(field, null);
}

function validateAll() {
    let ok = true;
    for (const field of Object.keys(VALIDATORS)) {
        if (!validateField(field)) ok = false;
    }
    return ok;
}

// The form lives inside <main class="overflow-y-auto"> — that element is the
// real scroll container, not window. Publish bar is at the bottom; on a failed
// submit (client OR server) the user needs to be jumped back to the error
// banner at the top of the form.
const mainRef = ref(null);
function scrollFormToTop() {
    mainRef.value?.scrollTo({ top: 0, behavior: 'smooth' });
}

provide('addListingForm', addListingForm);
provide('addListingFormErrors', addListingFormErrors);
provide('addListingFormValidate', { validateField, clearFieldError, validateAll });
provide('scrollFormToTop', scrollFormToTop);

// Server-side validation case: page loaded with errors already in the bag.
// Wait for the DOM (banner included) to render, then scroll into view.
onMounted(() => {
    if (errorCount.value > 0) {
        nextTick(() => scrollFormToTop());
    }
});
</script>

<template>
    <div class="h-screen flex overflow-hidden bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100">
        <AdminSidebar :user="user" />

        <div class="flex-1 flex flex-col min-w-0">
            <AdminTopBar
                title="Admin Add Listing"
                subtitle="Direct admin entry — published & verified immediately"
            />

            <main ref="mainRef" class="flex-1 overflow-y-auto p-6 lg:p-10">
                <AdminAddListingShortcutPill />
                <h1 class="mt-3 text-3xl font-bold text-gray-900 dark:text-white tracking-tight">
                    Admin Add Listing
                </h1>
                <p class="mt-2 text-gray-500 dark:text-gray-400">
                    Direct admin entry — published &amp; verified immediately. Skips broker review.
                </p>

                <div
                    v-if="errorCount > 0"
                    class="mt-6 rounded-md border border-red-200 dark:border-red-900/60 bg-red-50 dark:bg-red-950/30 px-4 py-3 flex items-start gap-3"
                    role="alert"
                >
                    <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 8v4M12 16h.01"/>
                    </svg>
                    <div class="text-sm text-red-800 dark:text-red-200">
                        <p class="font-medium">
                            Couldn't publish — {{ errorCount }} {{ errorCount === 1 ? 'field needs' : 'fields need' }} attention.
                        </p>
                        <p class="mt-0.5 text-xs text-red-700/80 dark:text-red-300/80">
                            Fix the highlighted fields below and try again.
                        </p>
                    </div>
                </div>

                <AdminAddListingFormCard
                    class="mt-6"
                    :listing-types="formData.listingTypes"
                    :barangays="formData.barangays"
                    :amenities="formData.amenities"
                />
            </main>

            <AdminAddListingPublishBar />
        </div>
    </div>
</template>
