<script setup>
import { ref, reactive, computed, provide, onMounted, nextTick } from 'vue';
import FieldSidebar from './components/field/FieldSidebar.vue';
import FieldTopBar from './components/field/FieldTopBar.vue';
import FieldEditListingFormCard from './components/field/FieldEditListingFormCard.vue';
import FieldRequestVerificationChecklist from './components/field/FieldRequestVerificationChecklist.vue';
import FieldRequestVerificationActionBar from './components/field/FieldRequestVerificationActionBar.vue';

const initial = window.__INITIAL_FIELD_REQUEST_VERIFICATION__ ?? {};

const user = ref(initial.user ?? {
    name:        'Field Officer',
    initials:    'FO',
    role_label:  'Field Officer',
    permissions: [],
});

const listing = ref(initial.listing ?? null);

const formData = ref({
    listingTypes: initial.listingTypes ?? [],
    barangays:    initial.barangays    ?? [],
    sourceSites:  initial.sourceSites  ?? [],
    contactTypes: initial.contactTypes ?? [],
    amenities:    initial.amenities    ?? [],
});

// On redirect-back from a failed submit, Laravel passes the submitted values
// via `oldInput`. Hydrate from there when present so the user sees their
// entries; fall back to the persisted listing on a fresh page load.
const oldInput = initial.oldInput ?? null;

const addListingForm = reactive({
    title:         oldInput?.title         ?? listing.value?.title         ?? '',
    description:   oldInput?.description   ?? listing.value?.description   ?? '',
    listing_type:  oldInput?.listing_type  ?? listing.value?.type          ?? '',
    price_monthly: oldInput
        ? (oldInput.price_monthly !== undefined && oldInput.price_monthly !== null && oldInput.price_monthly !== ''
            ? Number(oldInput.price_monthly) : null)
        : (listing.value?.price_monthly ?? null),
    barangay:      oldInput?.barangay      ?? listing.value?.barangay      ?? '',
    beds:          oldInput
        ? Number(oldInput.beds ?? 0)
        : (listing.value?.beds ?? 0),
    baths:         oldInput
        ? Number(oldInput.baths ?? 0)
        : (listing.value?.baths ?? 0),
    sqm:           oldInput
        ? Number(oldInput.sqm ?? 0)
        : (listing.value?.sqm ?? 0),
    latitude:      oldInput
        ? (oldInput.latitude !== undefined && oldInput.latitude !== null && oldInput.latitude !== ''
            ? Number(oldInput.latitude) : null)
        : (listing.value?.latitude ?? null),
    longitude:     oldInput
        ? (oldInput.longitude !== undefined && oldInput.longitude !== null && oldInput.longitude !== ''
            ? Number(oldInput.longitude) : null)
        : (listing.value?.longitude ?? null),
    directions:    oldInput?.directions    ?? listing.value?.directions    ?? '',
    amenities:     Array.isArray(oldInput?.amenities)
        ? oldInput.amenities.map(Number)
        : (Array.isArray(listing.value?.amenities)
            ? listing.value.amenities.map(a => Number(a.id))
            : []),
    photos:        Array.isArray(oldInput?.photos)
        ? oldInput.photos.map(p => ({
            existing_id: p.existing_id ? Number(p.existing_id) : undefined,
            key:         p.key,
            name:        p.name,
            size:        p.size,
            url:         p.url,
            sort_order:  0,
        }))
        : (Array.isArray(listing.value?.images)
            ? listing.value.images.map(img => ({ existing_id: img.id, url: img.url, sort_order: 0 }))
            : []),
});

const addListingFormErrors = ref(initial.errors ?? {});
const errorCount = computed(() => Object.keys(addListingFormErrors.value).length);

// Client-side validators mirror the server's request-verification rules.
// Stricter than the soft save: lat/lng required, ≥1 new photo (tmp/ key)
// required. Format/range checks fire when a value is provided. Server is the
// source of truth; this just saves the round-trip when failure is predictable.
const VALIDATORS = {
    title: (f) => {
        const v = (f.title ?? '').trim();
        if (!v) return 'Title is required.';
        if (v.length > 200) return 'Title is too long (max 200 characters).';
        return null;
    },
    listing_type: (f) => {
        if (!f.listing_type) return 'Listing type is required.';
        return null;
    },
    barangay: (f) => {
        if (!f.barangay) return 'Barangay is required.';
        return null;
    },
    price_monthly: (f) => {
        const empty = f.price_monthly === null || f.price_monthly === '' || f.price_monthly === undefined;
        if (empty) return null;
        const n = Number(f.price_monthly);
        if (!Number.isFinite(n)) return null;
        if (n < 1) return 'Monthly rent must be at least ₱1.';
        return null;
    },
    beds: (f) => {
        const empty = f.beds === null || f.beds === '' || f.beds === undefined;
        if (empty) return null;
        const n = Number(f.beds);
        if (!Number.isFinite(n)) return null;
        if (n < 0)  return 'The beds field must be at least 0.';
        if (n > 20) return 'The beds field must not be greater than 20.';
        return null;
    },
    baths: (f) => {
        const empty = f.baths === null || f.baths === '' || f.baths === undefined;
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
        if (n < 1) return 'Floor area must be at least 1 sqm.';
        return null;
    },
    latitude: (f) => {
        if (f.latitude === null || f.latitude === '' || f.latitude === undefined) {
            return 'Drop a map pin before requesting verification.';
        }
        const n = Number(f.latitude);
        if (!Number.isFinite(n)) return 'Drop a map pin before requesting verification.';
        if (n < -90 || n > 90) return 'Latitude must be between -90 and 90.';
        return null;
    },
    longitude: (f) => {
        if (f.longitude === null || f.longitude === '' || f.longitude === undefined) {
            return 'Drop a map pin before requesting verification.';
        }
        const n = Number(f.longitude);
        if (!Number.isFinite(n)) return 'Drop a map pin before requesting verification.';
        if (n < -180 || n > 180) return 'Longitude must be between -180 and 180.';
        return null;
    },
    directions: (f) => {
        const v = (f.directions ?? '').trim();
        if (!v) return 'Directions are required.';
        if (v.length > 500) return 'Directions are too long (max 500 characters).';
        return null;
    },
    photos: (f) => {
        if (!Array.isArray(f.photos) || f.photos.length === 0) {
            return 'Upload at least one new photo from your visit.';
        }
        if (f.photos.length > 20) return 'Maximum 20 photos allowed.';
        const hasNew = f.photos.some(p => p?.key && typeof p.key === 'string' && p.key.startsWith('tmp/'));
        if (!hasNew) return 'Upload at least one new photo from your visit.';
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

// Live prereqs computed from form state — checklist reflects in-page edits
// before submission, not the snapshot the resource captured server-side.
const livePrereqs = computed(() => ({
    has_title:              !!String(addListingForm.title ?? '').trim(),
    has_directions:         !!String(addListingForm.directions ?? '').trim(),
    has_lat_lng:            addListingForm.latitude !== null
                            && addListingForm.latitude !== ''
                            && addListingForm.longitude !== null
                            && addListingForm.longitude !== '',
    has_at_least_one_new_photo: Array.isArray(addListingForm.photos)
                                && addListingForm.photos.some(p => p?.key),
}));

const allPrereqsPass = computed(() => Object.values(livePrereqs.value).every(Boolean));
</script>

<template>
    <div v-if="listing" class="h-screen flex overflow-hidden bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100">
        <FieldSidebar :user="user" />

        <div class="flex-1 flex flex-col min-w-0">
            <FieldTopBar title="Request Verification" :subtitle="listing.title" />

            <main ref="mainRef" class="flex-1 overflow-y-auto px-6 pt-2 pb-6 lg:px-10 lg:pt-3 lg:pb-10">
                <a
                    href="/field/listings"
                    class="inline-flex items-center gap-1 text-sm text-orange-600 dark:text-orange-400 hover:text-orange-700 dark:hover:text-orange-300 mt-4 mb-2"
                >
                    ← Back to Listings
                </a>

                <div
                    v-if="errorCount > 0"
                    class="mt-4 rounded-lg border border-red-200 dark:border-red-900/50 bg-red-50 dark:bg-red-950/30 px-4 py-3"
                    role="alert"
                >
                    <p class="text-sm font-medium text-red-800 dark:text-red-300">
                        Please fix the {{ errorCount }} {{ errorCount === 1 ? 'issue' : 'issues' }} below before submitting.
                    </p>
                    <ul class="mt-1 text-sm text-red-700 dark:text-red-400 list-disc list-inside space-y-0.5">
                        <li v-for="(msgs, field) in addListingFormErrors" :key="field">
                            {{ Array.isArray(msgs) ? msgs[0] : msgs }}
                        </li>
                    </ul>
                </div>

                <FieldRequestVerificationChecklist
                    class="mt-4"
                    :prereqs="livePrereqs"
                />

                <FieldEditListingFormCard
                    class="mt-5"
                    :listing="listing"
                    :listing-types="formData.listingTypes"
                    :barangays="formData.barangays"
                    :source-sites="formData.sourceSites"
                    :contact-types="formData.contactTypes"
                    :amenities="formData.amenities"
                />
            </main>

            <FieldRequestVerificationActionBar
                :ready="allPrereqsPass"
                :listing="listing"
            />
        </div>
    </div>
</template>
