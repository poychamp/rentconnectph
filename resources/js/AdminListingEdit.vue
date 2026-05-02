<script setup>
import { ref, reactive, provide, computed, onMounted, nextTick } from 'vue';
import AdminSidebar from './components/admin/AdminSidebar.vue';
import AdminTopBar from './components/admin/AdminTopBar.vue';
import AdminAddListingFormCard from './components/admin/AdminAddListingFormCard.vue';
import AdminEditListingPublishBar from './components/admin/AdminEditListingPublishBar.vue';
import AdminEditListingUnverifiedPublishBar from './components/admin/AdminEditListingUnverifiedPublishBar.vue';
import AdminEditListingDangerZone from './components/admin/AdminEditListingDangerZone.vue';
import AdminEditListingRejectZone from './components/admin/AdminEditListingRejectZone.vue';
import AdminEditListingPrequalCard from './components/admin/AdminEditListingPrequalCard.vue';
import AdminEditListingCallContextCard from './components/admin/AdminEditListingCallContextCard.vue';
import AdminEditListingCallsContextCard from './components/admin/AdminEditListingCallsContextCard.vue';
import AdminEditListingLeadCard from './components/admin/AdminEditListingLeadCard.vue';

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

// Slice detection — the unverified-edit page renders extra cards
// (Pre-qualification, Call context). Driven by URL pathname so it works
// regardless of how the user landed on the page.
const isUnverifiedSlice = typeof window !== 'undefined'
    && window.location.pathname.endsWith('/unverified-edit');

// Persisted prequal state for the read-only badge — comes off the listing payload.
const prequalStatus = persisted?.prequal_status ?? null;

// Field-officer attribution scalars for the verified-slice banner card. Top-level
// keys on __INITIAL_EDIT_LISTING__ (not part of the editable form state).
const assignedToName = initial?.assignedToName ?? null;
const visitedAt      = initial?.visitedAt      ?? null;

// Origin tracking — picked up from ?from= on the edit URL (e.g.
// /admin/listings/{uuid}/edit?from=unverified). Round-tripped through the form
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
    // Pre-qualification + call-context fields. Editable on the unverified
    // slice via the Pre-qual card (status select) + Call-context card
    // (directions, contact type, notes, field-officer assignment). Server
    // doesn't validate or persist these yet — that's the next backend slice.
    prequal_status:     source.prequal_status     ?? '',
    directions:         source.directions         ?? '',
    contact_type:       source.contact_type       ?? '',
    verification_notes: source.verification_notes ?? '',
    assigned_to:        source.assigned_to ?? null,
    is_verified:  toBool(source.is_verified),
    is_featured:  toBool(source.is_featured),
    from:         initialFrom,
});

const addListingFormErrors = ref(initial?.errors ?? {});
const errorCount = computed(() => Object.keys(addListingFormErrors.value).length);

// Client-side validators mirror the server rules per slice. The required set
// on the unverified slice varies by prequal_status — not_called/no_answer
// require only title+contact_phone+prequal_status; called_yes (future) will
// extend with listing details + call-context fields. Format checks always run
// when a value is provided, regardless of slice. Server is the source of truth.
function isFieldRequired(field, prequalStatus) {
    if (!isUnverifiedSlice) {
        // Verified-slice required set: mirrors server-side rules in
        // Admin\ListingController::update. beds/baths/sqm are nullable.
        return ['title', 'listing_type', 'price_monthly', 'barangay', 'latitude', 'longitude', 'photos'].includes(field);
    }
    if (['title', 'contact_phone', 'prequal_status'].includes(field)) return true;
    if (prequalStatus === 'called_yes' && ['directions', 'contact_type'].includes(field)) return true;
    return false;
}

// Field set validateAll iterates per slice. Verified slice only includes
// editable inputs — locked calls-team / prequal fields are silent-ignored
// server-side and shouldn't trip client-side validation either.
const VERIFIED_SLICE_VALIDATABLE = [
    'title', 'listing_type', 'price_monthly', 'barangay',
    'beds', 'baths',
    'latitude', 'longitude',
    'photos',
];

const VALIDATORS = {
    title: (f) => {
        const v = (f.title ?? '').trim();
        if (!v && isFieldRequired('title', f.prequal_status)) return 'Title is required.';
        if (v.length > 200) return 'Title is too long (max 200 characters).';
        return null;
    },
    contact_phone: (f) => {
        const raw = (f.contact_phone ?? '').trim();
        if (!raw && isFieldRequired('contact_phone', f.prequal_status)) return 'Contact phone is required.';
        if (!raw) return null;

        // Mirror App\Support\PhMobile::normalize(). Accepts:
        //   09XXXXXXXXX (11 digits)
        //   9XXXXXXXXX  (10 digits)
        //   639XXXXXXXXX (12 digits)
        //   +639XXXXXXXXX (with +)
        const hasPlus = raw.startsWith('+');
        const digits = raw.replace(/\D/g, '');
        const isPh12 = digits.length === 12 && digits.startsWith('63') && digits[2] === '9';

        if (hasPlus) return isPh12 ? null : 'Invalid PH mobile number.';
        if (digits.length === 11 && digits.startsWith('09')) return null;
        if (isPh12) return null;
        if (digits.length === 10 && digits.startsWith('9')) return null;
        return 'Invalid PH mobile number.';
    },
    prequal_status: (f) => {
        if (!f.prequal_status && isFieldRequired('prequal_status', f.prequal_status)) {
            return 'Pre-qualification status is required.';
        }
        return null;
    },
    listing_type: (f) => {
        if (!f.listing_type && isFieldRequired('listing_type', f.prequal_status)) return 'Listing type is required.';
        return null;
    },
    price_monthly: (f) => {
        const empty = f.price_monthly === null || f.price_monthly === '' || f.price_monthly === undefined;
        if (empty && isFieldRequired('price_monthly', f.prequal_status)) return 'Monthly rent is required.';
        if (empty) return null;
        const n = Number(f.price_monthly);
        if (!Number.isFinite(n)) return null;
        if (n < 1) return 'Monthly rent must be at least ₱1.';
        return null;
    },
    barangay: (f) => {
        if (!f.barangay && isFieldRequired('barangay', f.prequal_status)) return 'Barangay is required.';
        return null;
    },
    beds: (f) => {
        const empty = f.beds === null || f.beds === '' || f.beds === undefined;
        if (empty && isFieldRequired('beds', f.prequal_status)) return 'Bedrooms is required.';
        if (empty) return null;
        const n = Number(f.beds);
        if (!Number.isFinite(n)) return null;
        if (n < 0)  return 'The beds field must be at least 0.';
        if (n > 20) return 'The beds field must not be greater than 20.';
        return null;
    },
    baths: (f) => {
        const empty = f.baths === null || f.baths === '' || f.baths === undefined;
        if (empty && isFieldRequired('baths', f.prequal_status)) return 'Bathrooms is required.';
        if (empty) return null;
        const n = Number(f.baths);
        if (!Number.isFinite(n)) return null;
        if (n < 0)  return 'The baths field must be at least 0.';
        if (n > 20) return 'The baths field must not be greater than 20.';
        return null;
    },
    latitude: (f) => {
        const empty = f.latitude === null || f.latitude === '' || f.latitude === undefined;
        if (empty && isFieldRequired('latitude', f.prequal_status)) return 'Latitude is required.';
        if (empty) return null;
        const n = Number(f.latitude);
        if (!Number.isFinite(n)) return null;
        if (n < -90 || n > 90) return 'Latitude must be between -90 and 90.';
        return null;
    },
    longitude: (f) => {
        const empty = f.longitude === null || f.longitude === '' || f.longitude === undefined;
        if (empty && isFieldRequired('longitude', f.prequal_status)) return 'Longitude is required.';
        if (empty) return null;
        const n = Number(f.longitude);
        if (!Number.isFinite(n)) return null;
        if (n < -180 || n > 180) return 'Longitude must be between -180 and 180.';
        return null;
    },
    photos: (f) => {
        const empty = !Array.isArray(f.photos) || f.photos.length === 0;
        if (empty && isFieldRequired('photos', f.prequal_status)) return 'At least one photo is required.';
        if (Array.isArray(f.photos) && f.photos.length > 20) return 'Maximum 20 photos allowed.';
        return null;
    },
    directions: (f) => {
        const v = (f.directions ?? '').trim();
        if (!v && isFieldRequired('directions', f.prequal_status)) return 'Directions are required.';
        if (v.length > 500) return 'Directions are too long (max 500 characters).';
        return null;
    },
    contact_type: (f) => {
        if (!f.contact_type && isFieldRequired('contact_type', f.prequal_status)) return 'Contact type is required.';
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
    const fields = isUnverifiedSlice
        ? Object.keys(VALIDATORS)
        : VERIFIED_SLICE_VALIDATABLE;
    let ok = true;
    for (const field of fields) {
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
                <AdminEditListingLeadCard
                    v-if="!isUnverifiedSlice"
                    class="mt-5"
                    :source-sites="formData.sourceSites"
                />

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

                <template v-if="isUnverifiedSlice">
                    <AdminEditListingPrequalCard
                        class="mt-6"
                        :contact-phone="addListingForm.contact_phone"
                        :persisted-prequal-status="prequalStatus"
                    />

                    <AdminEditListingCallContextCard
                        class="mt-5"
                        :contact-types="formData.contactTypes"
                        :field-users="formData.fieldUsers"
                    />
                </template>

                <AdminAddListingFormCard
                    class="mt-6"
                    :listing-types="formData.listingTypes"
                    :barangays="formData.barangays"
                    :source-sites="formData.sourceSites"
                    :amenities="formData.amenities"
                    :read-only-lead="!isUnverifiedSlice"
                    :hide-lead="!isUnverifiedSlice"
                />

                <AdminEditListingCallsContextCard
                    v-if="!isUnverifiedSlice"
                    class="mt-6"
                    :contact-types="formData.contactTypes"
                    :read-only="true"
                />

                <AdminEditListingDangerZone v-if="!isUnverifiedSlice" />
                <AdminEditListingRejectZone />
            </main>

            <AdminEditListingUnverifiedPublishBar v-if="isUnverifiedSlice" />
            <AdminEditListingPublishBar v-else />
        </div>
    </div>
</template>
