<script setup>
import { ref, reactive, provide, computed, onMounted, nextTick } from 'vue';
import AdminSidebar from './components/admin/AdminSidebar.vue';
import AdminTopBar from './components/admin/AdminTopBar.vue';
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
    name:        'Admin',
    initials:    'AD',
    role_label:  'Super Admin',
    permissions: [],
});

const formData = ref({
    listingTypes: initial?.listingTypes ?? [],
    barangays:    initial?.barangays    ?? [],
    sourceSites:  initial?.sourceSites  ?? [],
    amenities:    initial?.amenities    ?? [],
});

// Shared submit-payload state. Each child reads/writes its slice via inject('addListingForm').
// Pre-populated from `old()` when Laravel redirected back after a validation failure.
const addListingForm = reactive({
    title:        old?.title        ?? '',
    description:  old?.description  ?? '',
    listing_type: old?.listing_type ?? '',
    price_monthly: numOrNull(old?.price_monthly),
    barangay:     old?.barangay ?? '',
    beds:         numOr(old?.beds, 0),
    baths:        numOr(old?.baths, 0),
    sqm:          numOr(old?.sqm, 0),
    latitude:     numOrNull(old?.latitude),
    longitude:    numOrNull(old?.longitude),
    amenities:    Array.isArray(old?.amenities) ? old.amenities.map(Number) : [],
    photos:       Array.isArray(old?.photos)    ? old.photos                : [],
    source_site:   old?.source_site   ?? '',
    source_url:    old?.source_url    ?? '',
    contact_phone: old?.contact_phone ?? '',
});

// Per-field validation errors, keyed by Laravel field name (e.g. 'title', 'photos.0.key').
// Each value is an array of message strings — children render the first one.
const addListingFormErrors = ref(initial?.errors ?? {});

const errorCount = computed(() => Object.keys(addListingFormErrors.value).length);

// Client-side validators mirror the server rules in `Admin\ListingController::store()`,
// minus data-spoofing-shaped checks (enum allowlists `*.in`, photo key path
// `starts_with:tmp/`, amenity FK `exists`, intent `in:[...]`) — those failures
// can only happen via crafted requests, not via the UI, so adding mirrors only
// adds maintenance burden. Server still re-runs everything and is the source
// of truth.
const VALIDATORS = {
    title: (f) => {
        const v = (f.title ?? '').trim();
        if (!v) return 'Title is required.';
        if (v.length > 200) return 'Title is too long (max 200 characters).';
        return null;
    },
    price_monthly: (f) => {
        if (f.price_monthly === null || f.price_monthly === '' || f.price_monthly === undefined) return null;
        const n = Number(f.price_monthly);
        if (!Number.isFinite(n)) return null;
        if (n < 1) return 'Monthly rent must be at least ₱1.';
        return null;
    },
    beds: (f) => {
        if (f.beds === null || f.beds === '' || f.beds === undefined) return null;
        const n = Number(f.beds);
        if (!Number.isFinite(n)) return null;
        if (n < 0)  return 'The beds field must be at least 0.';
        if (n > 20) return 'The beds field must not be greater than 20.';
        return null;
    },
    baths: (f) => {
        if (f.baths === null || f.baths === '' || f.baths === undefined) return null;
        const n = Number(f.baths);
        if (!Number.isFinite(n)) return null;
        if (n < 0)  return 'The baths field must be at least 0.';
        if (n > 20) return 'The baths field must not be greater than 20.';
        return null;
    },
    sqm: (f) => {
        if (f.sqm === null || f.sqm === '' || f.sqm === undefined) return null;
        const n = Number(f.sqm);
        if (!Number.isFinite(n)) return null;
        if (n < 1) return 'Floor area must be at least 1 sqm.';
        return null;
    },
    latitude: (f) => {
        if (f.latitude === null || f.latitude === '' || f.latitude === undefined) return null;
        const n = Number(f.latitude);
        if (!Number.isFinite(n)) return null;
        if (n < -90 || n > 90) return 'Latitude must be between -90 and 90.';
        return null;
    },
    longitude: (f) => {
        if (f.longitude === null || f.longitude === '' || f.longitude === undefined) return null;
        const n = Number(f.longitude);
        if (!Number.isFinite(n)) return null;
        if (n < -180 || n > 180) return 'Longitude must be between -180 and 180.';
        return null;
    },
    photos: (f) => {
        if (Array.isArray(f.photos) && f.photos.length > 20) return 'Maximum 20 photos allowed.';
        return null;
    },
    source_url: (f) => {
        const v = (f.source_url ?? '').trim();
        if (!v) return null;
        if (v.length > 2000) return 'Source URL is too long (max 2000 characters).';
        try { new URL(v); } catch { return 'Source URL must be a valid URL.'; }
        return null;
    },
    // Mirrors PhMobile::normalize() in PHP — strip non-digits, classify shape,
    // return null when canonical +639XXXXXXXXX form is unreachable.
    contact_phone: (f) => {
        const v = (f.contact_phone ?? '').trim();
        if (!v) return 'Contact phone is required.';
        const hasPlus = v.startsWith('+');
        const digits  = v.replace(/\D/g, '');
        let canonical = null;
        if (hasPlus) {
            if (digits.length === 12 && digits.startsWith('63') && digits[2] === '9') canonical = '+' + digits;
        } else if (digits.length === 11 && digits.startsWith('09')) {
            canonical = '+63' + digits.slice(1);
        } else if (digits.length === 12 && digits.startsWith('63') && digits[2] === '9') {
            canonical = '+' + digits;
        } else if (digits.length === 10 && digits.startsWith('9')) {
            canonical = '+63' + digits;
        }
        return canonical === null ? 'Invalid PH mobile number.' : null;
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
            <AdminTopBar title="Add Listing" />

            <main ref="mainRef" class="flex-1 overflow-y-auto px-6 pt-2 pb-6 lg:px-10 lg:pt-3 lg:pb-10">
                <div
                    v-if="errorCount > 0"
                    class="rounded-md border border-red-200 dark:border-red-900/60 bg-red-50 dark:bg-red-950/30 px-4 py-3 flex items-start gap-3"
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
                    :source-sites="formData.sourceSites"
                    :amenities="formData.amenities"
                />
            </main>

            <AdminAddListingPublishBar />
        </div>
    </div>
</template>
