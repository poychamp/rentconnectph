<script setup>
import { ref, reactive, provide, computed, onMounted, nextTick } from 'vue';
import AdminSidebar from './components/admin/AdminSidebar.vue';
import AdminTopBar from './components/admin/AdminTopBar.vue';
import AdminEditUnverifiedListingFormCard from './components/admin/AdminEditUnverifiedListingFormCard.vue';
import AdminEditListingUnverifiedPublishBar from './components/admin/AdminEditListingUnverifiedPublishBar.vue';
import AdminEditListingRejectZone from './components/admin/AdminEditListingRejectZone.vue';
import AdminEditUnverifiedListingPrequalCard from './components/admin/AdminEditUnverifiedListingPrequalCard.vue';
import AdminEditUnverifiedListingCallContextCard from './components/admin/AdminEditUnverifiedListingCallContextCard.vue';

// Read initial state synchronously at setup so children inherit a fully-populated
// form on first render. oldInput (validation failure path) wins over listing
// (clean-load path) — same pattern as admin-create.
const initial = window.__INITIAL_EDIT_LISTING__ ?? null;
const old = initial?.oldInput ?? null;
const persisted = initial?.listing ?? null;
const source = old ?? persisted ?? {};

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
    fieldUsers:   initial?.fieldUsers   ?? [],
    contactTypes: initial?.contactTypes ?? [],
});

// Persisted prequal state for the read-only badge — comes off the listing payload.
const prequalStatus = persisted?.prequal_status ?? null;

const addListingForm = reactive({
    title:         source.title        ?? '',
    description:   source.description  ?? '',
    listing_type:  source.listing_type ?? '',
    price_monthly: numOrNull(source.price_monthly),
    barangay:      source.barangay ?? '',
    beds:          numOr(source.beds, 0),
    baths:         numOr(source.baths, 0),
    sqm:           numOr(source.sqm, 0),
    latitude:      numOrNull(source.latitude),
    longitude:     numOrNull(source.longitude),
    amenities:     Array.isArray(source.amenities) ? source.amenities.map(Number) : [],
    photos:        Array.isArray(source.photos)    ? source.photos                : [],
    source_site:   source.source_site   ?? '',
    source_url:    source.source_url    ?? '',
    contact: {
        uuid:  source.contact?.uuid  ?? '',
        phone: source.contact?.phone ?? '',
        name:  source.contact?.name  ?? '',
        notes: source.contact?.notes ?? '',
        is_show_name:  source.contact?.is_show_name  ?? true,
        is_show_notes: source.contact?.is_show_notes ?? true,
    },
    // Pre-qualification + call-context fields. Editable via the Pre-qual card
    // (status select) + Call-context card (directions, contact type, notes,
    // field-officer assignment).
    prequal_status:     source.prequal_status     ?? '',
    directions:         source.directions         ?? '',
    contact_type:       source.contact_type       ?? '',
    verification_notes: source.verification_notes ?? '',
    assigned_to:        source.assigned_to ?? null,
});

const addListingFormErrors = ref(initial?.errors ?? {});
const errorCount = computed(() => Object.keys(addListingFormErrors.value).length);

// Signals the contact card's find-by-phone lookup is in flight. Publish bar
// disables the Save button while pending so the admin doesn't submit a stale
// uuid/name/notes that the lookup is about to overwrite.
const contactPhoneLookupPending = ref(false);

// Toggle: flip to `false` to disable client-side validation. Server still
// re-validates as the source of truth either way. Matches the disable pattern
// used in `AuthProfile.vue` (CLIENT_VALIDATION_ENABLED).
const CLIENT_VALIDATION_ENABLED = true;

// Mirror of the server-side rule: persisted=called_yes silently ignores the
// contact section, so client validation also skips contact.* on this slice.
// Read the PERSISTED value, not live form state — admin can flip the prequal
// pill in-page without unlocking inputs mid-edit.
const isContactLocked = prequalStatus === 'called_yes';

// Client-side validators mirror the server rules in
// `Admin\ListingController::unverifiedUpdate()`, minus data-spoofing-shaped
// checks (enum allowlists `*.in`, photo key path `starts_with:tmp/`,
// amenity FK `exists`, photo existing_id `in:[...]`) — those failures can
// only happen via crafted requests, not via the UI, so adding mirrors only
// adds maintenance burden. Server still re-runs everything and is the
// source of truth.
const VALIDATORS = {
    title: (f) => {
        const v = (f.title ?? '').trim();
        if (!v) return 'Title is required.';
        if (v.length > 200) return 'Title is too long (max 200 characters).';
        return null;
    },
    prequal_status: (f) => {
        if (!f.prequal_status) return 'Pre-qualification status is required.';
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
        if (n < 0) return 'Floor area cannot be negative.';
        return null;
    },
    latitude: (f) => {
        const empty = f.latitude === null || f.latitude === '' || f.latitude === undefined;
        if (empty) return null;
        const n = Number(f.latitude);
        if (!Number.isFinite(n)) return null;
        if (n < -90 || n > 90) return 'Latitude must be between -90 and 90.';
        return null;
    },
    longitude: (f) => {
        const empty = f.longitude === null || f.longitude === '' || f.longitude === undefined;
        if (empty) return null;
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
        try {
            new URL(v);
        } catch {
            return 'Source URL must be a valid URL.';
        }
        if (v.length > 2000) return 'Source URL is too long (max 2000 characters).';
        return null;
    },
    'contact.phone': (f) => {
        if (isContactLocked) return null;
        const raw = (f.contact?.phone ?? '').trim();
        if (!raw) return 'Contact phone is required.';

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
    'contact.name': (f) => {
        if (isContactLocked) return null;
        const v = f.contact?.name ?? '';
        if (v.length > 120) return 'Contact name must be 120 characters or fewer.';
        return null;
    },
    'contact.notes': (f) => {
        if (isContactLocked) return null;
        const v = f.contact?.notes ?? '';
        if (v.length > 2000) return 'Contact notes must be 2000 characters or fewer.';
        return null;
    },
    directions: (f) => {
        const v = (f.directions ?? '').trim();
        if (f.prequal_status === 'called_yes' && !v) return 'Directions are required.';
        if (v.length > 500) return 'Directions are too long (max 500 characters).';
        return null;
    },
    contact_type: (f) => {
        if (f.prequal_status === 'called_yes' && !f.contact_type) return 'Contact type is required.';
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
    if (!CLIENT_VALIDATION_ENABLED) return true;
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
    if (!CLIENT_VALIDATION_ENABLED) return true;
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
provide('contactPhoneLookupPending', contactPhoneLookupPending);
// Server-persisted prequal status — feeds the contact card's lock-in (mirror
// of the server-side rule: persisted=called_yes silently ignores the contact
// section, so the UI freezes inputs to match).
provide('persistedPrequalStatus', prequalStatus);

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
                title="Edit Unverified Listing"
                subtitle="Update lead details, call context, and field-officer assignment"
            />

            <main ref="mainRef" class="flex-1 overflow-y-auto p-6 lg:p-8">
                <div
                    v-if="errorCount > 0"
                    class="mb-6 rounded-md border border-red-200 dark:border-red-900/60 bg-red-50 dark:bg-red-950/30 px-4 py-3 flex items-start gap-3"
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

                <AdminEditUnverifiedListingPrequalCard
                    :persisted-prequal-status="prequalStatus"
                />

                <AdminEditUnverifiedListingCallContextCard
                    class="mt-5"
                    :contact-types="formData.contactTypes"
                    :field-users="formData.fieldUsers"
                />

                <AdminEditUnverifiedListingFormCard
                    class="mt-6"
                    :listing-types="formData.listingTypes"
                    :barangays="formData.barangays"
                    :source-sites="formData.sourceSites"
                    :amenities="formData.amenities"
                />

                <AdminEditListingRejectZone />
            </main>

            <AdminEditListingUnverifiedPublishBar />
        </div>
    </div>
</template>
