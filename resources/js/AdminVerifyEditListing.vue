<script setup>
import { ref, reactive, provide, computed, onMounted, nextTick } from 'vue';
import AdminSidebar from './components/admin/AdminSidebar.vue';
import AdminTopBar from './components/admin/AdminTopBar.vue';
import AdminAddListingFormCard from './components/admin/AdminAddListingFormCard.vue';
import AdminVerifyEditListingFieldOfficerCard from './components/admin/AdminVerifyEditListingFieldOfficerCard.vue';
import AdminVerifyEditListingContactCard from './components/admin/AdminVerifyEditListingContactCard.vue';
import AdminVerifyEditListingSourceCard from './components/admin/AdminVerifyEditListingSourceCard.vue';
import AdminVerifyEditListingCallsContextCard from './components/admin/AdminVerifyEditListingCallsContextCard.vue';
import AdminVerifyEditListingPublishBar from './components/admin/AdminVerifyEditListingPublishBar.vue';
import AdminVerifyEditListingRejectZone from './components/admin/AdminVerifyEditListingRejectZone.vue';

const initial   = window.__INITIAL_VERIFY_EDIT_LISTING__ ?? null;
const old       = initial?.oldInput ?? null;
const persisted = initial?.listing  ?? null;
const source    = old ?? persisted ?? {};

const numOrNull = (v) => v === undefined || v === null || v === '' ? null : Number(v);
const numOr = (v, fallback) => v === undefined || v === null || v === '' ? fallback : (Number(v) || fallback);
const toBool = (v) => v === true || v === '1' || v === 1;

const user = ref(initial?.user ?? {
    name:        'Admin',
    initials:    'AD',
    role_label:  'Super Admin',
    permissions: [],
});

const formData = ref({
    listingTypes: initial?.listingTypes ?? [],
    barangays:    initial?.barangays    ?? [],
    sourceSites:  initial?.sourceSites  ?? [],
    contactTypes: initial?.contactTypes ?? [],
    amenities:    initial?.amenities    ?? [],
});

// Form binding — prefer oldInput on validation-failure round-trip, fall back to the
// persisted listing payload otherwise. Mirrors AdminListingEdit's hydration shape.
const addListingForm = reactive({
    title:              source.title              ?? '',
    description:        source.description        ?? '',
    listing_type:       source.listing_type       ?? source.type ?? '',
    price_monthly:      numOrNull(source.price_monthly),
    barangay:           source.barangay           ?? '',
    beds:               numOr(source.beds, 0),
    baths:              numOr(source.baths, 0),
    sqm:                numOr(source.sqm, 0),
    latitude:           numOrNull(source.latitude),
    longitude:          numOrNull(source.longitude),
    amenities:          Array.isArray(source.amenities)
        ? source.amenities.map(a => typeof a === 'object' ? a.id : Number(a))
        : [],
    photos:             Array.isArray(source.photos)
        ? source.photos
        : (Array.isArray(source.images)
            ? source.images.map(img => ({ existing_id: img.id, url: img.url, sort_order: img.sort_order }))
            : []),
    // Field-captured + calls-team-context (editable on this surface)
    contact_phone:      source.contact_phone      ?? '',
    contact_type:       source.contact_type       ?? '',
    source_site:        source.source_site        ?? '',
    source_url:         source.source_url         ?? '',
    directions:         source.directions         ?? '',
    verification_notes: source.verification_notes ?? '',
    // Moderation toggle — admin can verify-and-feature in one shot
    is_featured:        toBool(source.is_featured),
});

// Server is the source of truth — errors land in the 'verify' named bag from
// the controller and Blade injects them as `verifyErrors`. Client-side
// validators below pre-empt for fast feedback; both populate the same ref.
const addListingFormErrors = ref(initial?.verifyErrors ?? initial?.errors ?? {});
const errorCount = computed(() => Object.keys(addListingFormErrors.value).length);

// `errors.listing` is the slice-guard failure ("This listing cannot be verified.")
// — surfaces through the same named bag but doesn't map to a per-field input.
const listingError = computed(() => addListingFormErrors.value.listing?.[0] ?? null);

// Client-side validators mirror the server's PUT /verify rules. Calls-team-locked
// fields (contact_phone) are silent-ignored server-side → no validator. Directions
// is server-validated but read-only in UI (admin can't fix from this page) → no
// validator either, to avoid stuck-state UX.
const VALIDATORS = {
    title: (f) => {
        const v = (f.title ?? '').trim();
        if (!v) return 'Title is required.';
        if (v.length > 200) return 'Title is too long (max 200 characters).';
        return null;
    },
    description: (f) => {
        const v = (f.description ?? '').trim();
        if (v.length > 5000) return 'Description is too long (max 5000 characters).';
        return null;
    },
    listing_type: (f) => f.listing_type ? null : 'Listing type is required.',
    price_monthly: (f) => {
        if (f.price_monthly === null || f.price_monthly === '' || f.price_monthly === undefined) {
            return 'Monthly rent is required.';
        }
        const n = Number(f.price_monthly);
        if (!Number.isFinite(n) || n < 1) return 'Monthly rent must be at least ₱1.';
        return null;
    },
    barangay: (f) => f.barangay ? null : 'Barangay is required.',
    beds: (f) => {
        if (f.beds === null || f.beds === '' || f.beds === undefined) return null;
        const n = Number(f.beds);
        if (!Number.isFinite(n)) return null;
        if (n < 0 || n > 20) return 'Bedrooms must be between 0 and 20.';
        return null;
    },
    baths: (f) => {
        if (f.baths === null || f.baths === '' || f.baths === undefined) return null;
        const n = Number(f.baths);
        if (!Number.isFinite(n)) return null;
        if (n < 0 || n > 20) return 'Bathrooms must be between 0 and 20.';
        return null;
    },
    sqm: (f) => {
        if (f.sqm === null || f.sqm === '' || f.sqm === undefined || f.sqm === 0) return null;
        const n = Number(f.sqm);
        if (!Number.isFinite(n)) return null;
        if (n < 1) return 'Floor area must be at least 1 sqm.';
        return null;
    },
    latitude: (f) => {
        if (f.latitude === null || f.latitude === '' || f.latitude === undefined) {
            return 'Map pin is required — drop one before verifying.';
        }
        const n = Number(f.latitude);
        if (!Number.isFinite(n)) return 'Latitude must be a number.';
        if (n < -90 || n > 90) return 'Latitude must be between -90 and 90.';
        return null;
    },
    longitude: (f) => {
        if (f.longitude === null || f.longitude === '' || f.longitude === undefined) {
            return 'Map pin is required — drop one before verifying.';
        }
        const n = Number(f.longitude);
        if (!Number.isFinite(n)) return 'Longitude must be a number.';
        if (n < -180 || n > 180) return 'Longitude must be between -180 and 180.';
        return null;
    },
    amenities: (f) => {
        if (!Array.isArray(f.amenities)) return null;
        if (f.amenities.length > 50) return 'Maximum 50 amenities allowed.';
        return null;
    },
    photos: (f) => {
        const empty = !Array.isArray(f.photos) || f.photos.length === 0;
        if (empty) return 'At least one photo is required.';
        if (f.photos.length > 20) return 'Maximum 20 photos allowed.';
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
    <div class="h-screen flex overflow-hidden bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100">
        <AdminSidebar :user="user" />

        <div class="flex-1 flex flex-col min-w-0">
            <AdminTopBar
                title="Verify Listing"
                subtitle="Review the field officer's submission and approve for public listing"
            />

            <main ref="mainRef" class="flex-1 overflow-y-auto p-6 lg:p-8 space-y-5">
                <AdminVerifyEditListingFieldOfficerCard
                    :assigned-to-name="persisted?.assigned_to_name"
                    :visited-at="persisted?.visited_at"
                />

                <div
                    v-if="errorCount > 0"
                    class="rounded-md border border-red-200 dark:border-red-900/60 bg-red-50 dark:bg-red-950/30 px-4 py-3 flex items-start gap-3"
                    role="alert"
                >
                    <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 8v4M12 16h.01"/>
                    </svg>
                    <div class="text-sm text-red-800 dark:text-red-200 flex-1 min-w-0">
                        <p class="font-medium">
                            Couldn't verify — {{ errorCount }} {{ errorCount === 1 ? 'issue' : 'issues' }} need attention.
                        </p>
                        <p v-if="listingError" class="mt-1 text-sm">
                            {{ listingError }}
                        </p>
                        <ul class="mt-1 text-xs text-red-700/80 dark:text-red-300/80 space-y-0.5 list-disc list-inside">
                            <li v-for="(messages, field) in addListingFormErrors" :key="field">
                                <span class="font-medium">{{ field }}:</span> {{ messages[0] }}
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    <AdminVerifyEditListingContactCard :listing="persisted" />
                    <AdminVerifyEditListingSourceCard :listing="persisted" :source-sites="formData.sourceSites" />
                </div>

                <AdminAddListingFormCard
                    :listing-types="formData.listingTypes"
                    :barangays="formData.barangays"
                    :source-sites="formData.sourceSites"
                    :amenities="formData.amenities"
                    :hide-lead="true"
                />

                <AdminVerifyEditListingCallsContextCard
                    :contact-types="formData.contactTypes"
                    :read-only="true"
                    :verification-notes-editable="true"
                />

                <AdminVerifyEditListingRejectZone />
            </main>

            <AdminVerifyEditListingPublishBar />
        </div>
    </div>
</template>
