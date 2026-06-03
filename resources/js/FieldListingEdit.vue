<script setup>
import { ref, reactive, computed, provide, onMounted, nextTick } from 'vue';
import FieldSidebar from './components/field/FieldSidebar.vue';
import FieldTopBar from './components/field/FieldTopBar.vue';
import FieldEditListingFormCard from './components/field/FieldEditListingFormCard.vue';
import FieldEditListingPublishBar from './components/field/FieldEditListingPublishBar.vue';

const initial = window.__INITIAL_FIELD_LISTING_EDIT__ ?? {};

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

const fromQuery = new URLSearchParams(window.location.search).get('from');
const backHref = fromQuery === 'priority' ? '/field/priority' : '/field/listings';
const backLabel = fromQuery === 'priority' ? '← Back to Priority' : '← Back to Listings';

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
    verification_notes: oldInput?.verification_notes ?? listing.value?.verification_notes ?? '',
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

// Client-side validators mirror the server rules. Only `title` is required
// in this slice — everything else is nullable. Format/range checks fire when
// a value is provided. Server is the source of truth; this just saves the
// round-trip when failure is predictable. Enum allowlists, photo `tmp/`
// prefix, and amenity exists checks are server-only — those failures only
// happen via crafted requests, not via UI.
const VALIDATORS = {
    title: (f) => {
        const v = (f.title ?? '').trim();
        if (!v) return 'Title is required.';
        if (v.length > 200) return 'Title is too long (max 200 characters).';
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
        const empty = f.sqm === null || f.sqm === '' || f.sqm === undefined || f.sqm === 0;
        if (empty) return null;
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
        if (Array.isArray(f.photos) && f.photos.length > 20) {
            return 'Maximum 20 photos allowed.';
        }
        return null;
    },
    directions: (f) => {
        const v = (f.directions ?? '').trim();
        if (!v) return 'Directions are required.';
        if (v.length > 500) return 'Directions are too long (max 500 characters).';
        return null;
    },
    verification_notes: (f) => {
        if ((f.verification_notes ?? '').length > 2000) return 'Verification notes must be 2000 characters or fewer.';
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
    <div v-if="listing" class="h-screen flex overflow-hidden bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100">
        <FieldSidebar :user="user" />

        <div class="flex-1 flex flex-col min-w-0">
            <FieldTopBar title="Listing Details" :subtitle="listing.title" />

            <main ref="mainRef" class="flex-1 overflow-y-auto p-6 lg:p-8">
                <div
                    v-if="errorCount > 0"
                    class="mb-4 rounded-lg border border-red-200 dark:border-red-900/50 bg-red-50 dark:bg-red-950/30 px-4 py-3"
                    role="alert"
                >
                    <p class="text-sm font-medium text-red-800 dark:text-red-300">
                        Please fix the {{ errorCount }} {{ errorCount === 1 ? 'issue' : 'issues' }} below before saving.
                    </p>
                    <ul class="mt-1 text-sm text-red-700 dark:text-red-400 list-disc list-inside space-y-0.5">
                        <li v-for="(msgs, field) in addListingFormErrors" :key="field">
                            {{ Array.isArray(msgs) ? msgs[0] : msgs }}
                        </li>
                    </ul>
                </div>

                <FieldEditListingFormCard
                    :listing="listing"
                    :listing-types="formData.listingTypes"
                    :barangays="formData.barangays"
                    :source-sites="formData.sourceSites"
                    :contact-types="formData.contactTypes"
                    :amenities="formData.amenities"
                />
            </main>

            <FieldEditListingPublishBar :listing="listing" />
        </div>
    </div>
</template>
