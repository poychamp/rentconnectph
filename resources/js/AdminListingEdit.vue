<script setup>
import { ref, reactive, provide, computed, onMounted, nextTick } from 'vue';
import AdminSidebar from './components/admin/AdminSidebar.vue';
import AdminTopBar from './components/admin/AdminTopBar.vue';
import AdminAddListingFormCard from './components/admin/AdminAddListingFormCard.vue';
import AdminEditListingPublishBar from './components/admin/AdminEditListingPublishBar.vue';
import AdminEditListingDangerZone from './components/admin/AdminEditListingDangerZone.vue';
import AdminEditListingRejectZone from './components/admin/AdminEditListingRejectZone.vue';
import AdminEditListingCallsContextCard from './components/admin/AdminEditListingCallsContextCard.vue';
import AdminEditListingContactCard from './components/admin/AdminEditListingContactCard.vue';
import AdminEditListingSourceCard from './components/admin/AdminEditListingSourceCard.vue';

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
    fieldUsers:   initial?.fieldUsers   ?? [],
    contactTypes: initial?.contactTypes ?? [],
});

// Field-officer attribution scalars for the verified-slice banner card. Top-level
// keys on __INITIAL_EDIT_LISTING__ (not part of the editable form state).
const assignedToName = initial?.assignedToName ?? null;
const visitedAt      = initial?.visitedAt      ?? null;

// Origin tracking — picked up from ?from= on the edit URL (e.g.
// /admin/listings/{uuid}/edit?from=verified). Round-tripped through the form
// so the update handler can redirect back to the page the admin was editing
// from. Survives validation-failure redirect-back via oldInput.from.
const fromParam = (new URLSearchParams(window.location.search)).get('from');
const initialFrom = old?.from ?? fromParam ?? '';

const addListingForm = reactive({
    title:        source.title        ?? '',
    description:  source.description  ?? '',
    listing_type: source.listing_type ?? '',
    price_monthly: numOrNull(source.price_monthly),
    barangay:     source.barangay ?? '',
    beds:         numOr(source.beds, 0),
    baths:        numOr(source.baths, 0),
    sqm:          numOr(source.sqm, 0),
    latitude:     numOrNull(source.latitude),
    longitude:    numOrNull(source.longitude),
    amenities:    Array.isArray(source.amenities) ? source.amenities.map(Number) : [],
    photos:       Array.isArray(source.photos)    ? source.photos                : [],
    source_site:   source.source_site   ?? '',
    source_url:    source.source_url    ?? '',
    contact_phone: source.contact_phone ?? '',
    directions:         source.directions         ?? '',
    contact_type:       source.contact_type       ?? '',
    verification_notes: source.verification_notes ?? '',
    is_verified:  toBool(source.is_verified),
    is_featured:  toBool(source.is_featured),
    from:         initialFrom,
});

const addListingFormErrors = ref(initial?.errors ?? {});
const errorCount = computed(() => Object.keys(addListingFormErrors.value).length);

// Verified-slice required set: mirrors server-side rules in
// Admin\ListingController::update. beds/baths/sqm are nullable.
function isFieldRequired(field) {
    return ['title', 'listing_type', 'price_monthly', 'barangay', 'latitude', 'longitude', 'photos'].includes(field);
}

// Field set validateAll iterates — locked calls-team fields are silent-ignored
// server-side and shouldn't trip client-side validation either.
const VALIDATABLE = [
    'title', 'listing_type', 'price_monthly', 'barangay',
    'beds', 'baths', 'sqm',
    'latitude', 'longitude',
    'photos',
];

const VALIDATORS = {
    title: (f) => {
        const v = (f.title ?? '').trim();
        if (!v && isFieldRequired('title')) return 'Title is required.';
        if (v.length > 200) return 'Title is too long (max 200 characters).';
        return null;
    },
    description: (f) => {
        const v = (f.description ?? '').trim();
        if (v.length > 5000) return 'Description is too long (max 5000 characters).';
        return null;
    },
    listing_type: (f) => {
        if (!f.listing_type && isFieldRequired('listing_type')) return 'Listing type is required.';
        return null;
    },
    price_monthly: (f) => {
        const empty = f.price_monthly === null || f.price_monthly === '' || f.price_monthly === undefined;
        if (empty && isFieldRequired('price_monthly')) return 'Monthly rent is required.';
        if (empty) return null;
        const n = Number(f.price_monthly);
        if (!Number.isFinite(n)) return null;
        if (n < 1) return 'Monthly rent must be at least ₱1.';
        return null;
    },
    barangay: (f) => {
        if (!f.barangay && isFieldRequired('barangay')) return 'Barangay is required.';
        return null;
    },
    beds: (f) => {
        const empty = f.beds === null || f.beds === '' || f.beds === undefined;
        if (empty && isFieldRequired('beds')) return 'Bedrooms is required.';
        if (empty) return null;
        const n = Number(f.beds);
        if (!Number.isFinite(n)) return null;
        if (n < 0)  return 'The beds field must be at least 0.';
        if (n > 20) return 'The beds field must not be greater than 20.';
        return null;
    },
    baths: (f) => {
        const empty = f.baths === null || f.baths === '' || f.baths === undefined;
        if (empty && isFieldRequired('baths')) return 'Bathrooms is required.';
        if (empty) return null;
        const n = Number(f.baths);
        if (!Number.isFinite(n)) return null;
        if (n < 0)  return 'The baths field must be at least 0.';
        if (n > 20) return 'The baths field must not be greater than 20.';
        return null;
    },
    sqm: (f) => {
        const empty = f.sqm === null || f.sqm === '' || f.sqm === undefined;
        if (empty) return null;
        const n = Number(f.sqm);
        if (!Number.isFinite(n)) return null;
        if (n < 0) return 'Floor area cannot be negative.';
        return null;
    },
    latitude: (f) => {
        const empty = f.latitude === null || f.latitude === '' || f.latitude === undefined;
        if (empty && isFieldRequired('latitude')) return 'Latitude is required.';
        if (empty) return null;
        const n = Number(f.latitude);
        if (!Number.isFinite(n)) return null;
        if (n < -90 || n > 90) return 'Latitude must be between -90 and 90.';
        return null;
    },
    longitude: (f) => {
        const empty = f.longitude === null || f.longitude === '' || f.longitude === undefined;
        if (empty && isFieldRequired('longitude')) return 'Longitude is required.';
        if (empty) return null;
        const n = Number(f.longitude);
        if (!Number.isFinite(n)) return null;
        if (n < -180 || n > 180) return 'Longitude must be between -180 and 180.';
        return null;
    },
    photos: (f) => {
        const empty = !Array.isArray(f.photos) || f.photos.length === 0;
        if (empty && isFieldRequired('photos')) return 'At least one photo is required.';
        if (Array.isArray(f.photos) && f.photos.length > 20) return 'Maximum 20 photos allowed.';
        return null;
    },
    verification_notes: (f) => {
        const v = (f.verification_notes ?? '').trim();
        if (v.length > 2000) return 'Verification notes are too long (max 2000 characters).';
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
    for (const field of VALIDATABLE) {
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

            <main ref="mainRef" class="flex-1 overflow-y-auto px-6 pt-2 pb-6 lg:px-10 lg:pt-3 lg:pb-10">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mt-5">
                    <AdminEditListingContactCard :listing="persisted" />
                    <AdminEditListingSourceCard :listing="persisted" :source-sites="formData.sourceSites" />
                </div>

                <div
                    v-if="errorCount > 0"
                    class="mt-6 rounded-md border border-red-200 dark:border-red-900/60 bg-red-50 dark:bg-red-950/30 px-4 py-3 flex items-start gap-3"
                    role="alert"
                >
                    <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 8v4M12 16h.01"/>
                    </svg>
                    <div class="text-sm text-red-800 dark:text-red-200 flex-1 min-w-0">
                        <p class="font-medium">
                            Couldn't save — {{ errorCount }} {{ errorCount === 1 ? 'issue' : 'issues' }} need attention.
                        </p>
                        <ul class="mt-1 text-xs text-red-700/80 dark:text-red-300/80 space-y-0.5 list-disc list-inside">
                            <li v-for="(messages, field) in addListingFormErrors" :key="field">
                                <span class="font-medium">{{ field }}:</span> {{ messages[0] }}
                            </li>
                        </ul>
                    </div>
                </div>

                <AdminAddListingFormCard
                    class="mt-6"
                    :listing-types="formData.listingTypes"
                    :barangays="formData.barangays"
                    :source-sites="formData.sourceSites"
                    :amenities="formData.amenities"
                    :hide-lead="true"
                />

                <AdminEditListingCallsContextCard
                    class="mt-6"
                    :contact-types="formData.contactTypes"
                    :read-only="true"
                    :verification-notes-editable="true"
                />

                <AdminEditListingDangerZone />
                <AdminEditListingRejectZone />
            </main>

            <AdminEditListingPublishBar />
        </div>
    </div>
</template>
