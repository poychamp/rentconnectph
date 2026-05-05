import './bootstrap';
import './axios';

import { createApp } from 'vue';
import PrimeVue from 'primevue/config';
import { RentConnectPreset } from './primevue';
import AdminToast from './components/admin/AdminToast.vue';

const el = document.getElementById('app');

if (el) {
    const page = el.dataset.page || 'auth-login';

    const roots = {
        'auth-login':               () => import('./AuthLogin.vue'),
        'admin-dashboard':          () => import('./AdminDashboard.vue'),
        'add-listing':              () => import('./AdminListingCreate.vue'),
        'admin-edit-listing':       () => import('./AdminListingEdit.vue'),
        'admin-verified-listings':  () => import('./AdminVerifiedListings.vue'),
        'admin-unverified-listings': () => import('./AdminUnverifiedListings.vue'),
        'admin-visited-listings':   () => import('./AdminVisitedListings.vue'),
        'admin-verify-edit-listing': () => import('./AdminVerifyEditListing.vue'),
        'admin-deactivated-listings': () => import('./AdminDeactivatedListings.vue'),
        'admin-restore-listing':    () => import('./AdminListingRestore.vue'),
        'admin-rejected-listings':  () => import('./AdminRejectedListings.vue'),
        'admin-reopen-listing':     () => import('./AdminListingReopen.vue'),
        'admin-featured-listings':  () => import('./AdminFeaturedListings.vue'),
        'admin-amenities':          () => import('./AdminAmenities.vue'),
        'admin-filtered-inquiries': () => import('./AdminFilteredInquiries.vue'),
        'admin-inquiries':          () => import('./AdminInquiries.vue'),
        'admin-handoffs':           () => import('./AdminHandoffs.vue'),
        'admin-leads':              () => import('./AdminLeads.vue'),
        'admin-amenity-create':     () => import('./AdminAmenityCreate.vue'),
        'admin-amenity-edit':       () => import('./AdminAmenityEdit.vue'),
        'admin-deleted-amenities':  () => import('./AdminDeletedAmenities.vue'),
        'field-dashboard':          () => import('./FieldDashboard.vue'),
        'field-listings':           () => import('./FieldListings.vue'),
        'field-listing-edit':       () => import('./FieldListingEdit.vue'),
        'field-request-verification': () => import('./FieldRequestVerification.vue'),
        'field-priority':           () => import('./FieldPriority.vue'),
        'field-submitted-listings': () => import('./FieldSubmittedListings.vue'),
        'field-listing-preview':    () => import('./FieldListingPreview.vue'),
        'field-verified-listings':  () => import('./FieldVerifiedListings.vue'),
    };

    const loadRoot = roots[page] || roots['auth-login'];

    loadRoot().then(({ default: Root }) => {
        createApp(Root)
            .use(PrimeVue, {
                theme: {
                    preset: RentConnectPreset,
                    options: { darkModeSelector: '.dark' },
                },
            })
            .mount('#app');
    });
}

// Toast — separate Vue root mounted on every admin page. Reads window.__FLASH__
// (injected by layouts/admin.blade.php) and shows a toast for any session
// success/error/info flash, then auto-dismisses after 4s.
const toastEl = document.getElementById('admin-toast');
if (toastEl) {
    createApp(AdminToast).mount('#admin-toast');
}
