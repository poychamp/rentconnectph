<script setup>
import { ref, reactive, provide, computed, onMounted, nextTick } from 'vue';
import AdminSidebar from './components/admin/AdminSidebar.vue';
import AdminTopBar from './components/admin/AdminTopBar.vue';
import AdminAddListingShortcutPill from './components/admin/AdminAddListingShortcutPill.vue';
import AdminAddListingFormCard from './components/admin/AdminAddListingFormCard.vue';
import AdminEditListingPublishBar from './components/admin/AdminEditListingPublishBar.vue';

// Read initial state synchronously at setup so children inherit a fully-populated
// form on first render. oldInput (validation failure path) wins over listing
// (clean-load path) — same pattern as admin-create.
const initial = window.__INITIAL_EDIT_LISTING__ ?? null;
const old = initial?.oldInput ?? null;
const persisted = initial?.listing ?? null;
const source = old ?? persisted ?? {};

const numOrNull = (v) => v === undefined || v === null || v === '' ? null : Number(v);
const numOr = (v, fallback) => v === undefined || v === null || v === '' ? fallback : (Number(v) || fallback);
const toBool = (v) => v === true || v === '1' || v === 1;

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

const addListingForm = reactive({
    title:        source.title        ?? '',
    description:  source.description  ?? '',
    listing_type: source.listing_type ?? '',
    monthly_rent: numOrNull(source.monthly_rent),
    barangay:     source.barangay ?? '',
    beds:         numOr(source.beds, 0),
    baths:        numOr(source.baths, 0),
    sqft:         numOr(source.sqft, 0),
    latitude:     numOrNull(source.latitude),
    longitude:    numOrNull(source.longitude),
    amenities:    Array.isArray(source.amenities) ? source.amenities.map(Number) : [],
    photos:       Array.isArray(source.photos)    ? source.photos                : [],
    is_verified:  toBool(source.is_verified),
    is_featured:  toBool(source.is_featured),
});

const addListingFormErrors = ref(initial?.errors ?? {});
const errorCount = computed(() => Object.keys(addListingFormErrors.value).length);

// Client-side validators mirror the confirmed server-side messages exactly so
// the UX stays consistent whether the failure was caught locally or after the
// round-trip. Server is still the source of truth — anything not listed here
// (listing_type required, monthly_rent min:1, beds/baths max:20, amenities
// rules, etc.) falls through to the server.
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
        return null;
    },
    barangay: (f) => {
        const allowed = (window.__INITIAL_EDIT_LISTING__?.barangays ?? []).map(b => b.value);
        if (!f.barangay || !allowed.includes(f.barangay)) return 'Invalid barangay.';
        return null;
    },
    beds: (f) => {
        const n = Number(f.beds);
        if (!Number.isFinite(n) || n < 0) return 'The beds field must be at least 0.';
        return null;
    },
    baths: (f) => {
        const n = Number(f.baths);
        if (!Number.isFinite(n) || n < 0) return 'The baths field must be at least 0.';
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
        if (!Number.isFinite(n) || n < -90 || n > 90) return 'Latitude must be between -90 and 90.';
        return null;
    },
    longitude: (f) => {
        if (f.longitude === null || f.longitude === '' || f.longitude === undefined) return null;
        const n = Number(f.longitude);
        if (!Number.isFinite(n) || n < -180 || n > 180) return 'Longitude must be between -180 and 180.';
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

const mainRef = ref(null);
function scrollFormToTop() {
    mainRef.value?.scrollTo({ top: 0, behavior: 'smooth' });
}

provide('addListingForm', addListingForm);
provide('addListingFormErrors', addListingFormErrors);
provide('addListingFormValidate', { validateField, clearFieldError, validateAll });
provide('scrollFormToTop', scrollFormToTop);

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
                title="Edit Listing"
                subtitle="Update listing details, photos, amenities, and verification state"
            />

            <main ref="mainRef" class="flex-1 overflow-y-auto p-6 lg:p-10">
                <AdminAddListingShortcutPill />
                <h1 class="mt-3 text-3xl font-bold text-gray-900 dark:text-white tracking-tight">
                    Edit Listing
                </h1>
                <p class="mt-2 text-gray-500 dark:text-gray-400">
                    Saved changes apply immediately. Toggling verification off removes the listing from public discovery.
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
                            Couldn't save — {{ errorCount }} {{ errorCount === 1 ? 'field needs' : 'fields need' }} attention.
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

            <AdminEditListingPublishBar />
        </div>
    </div>
</template>
