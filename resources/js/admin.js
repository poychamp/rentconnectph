import './bootstrap';
import './axios';

import { createApp } from 'vue';
import PrimeVue from 'primevue/config';
import { RentConnectPreset } from './primevue';
import AdminToast from './components/admin/AdminToast.vue';

const el = document.getElementById('app');

if (el) {
    const page = el.dataset.page || 'admin-login';

    const roots = {
        'admin-login':              () => import('./AdminLogin.vue'),
        'admin-dashboard':          () => import('./AdminDashboard.vue'),
        'add-listing':              () => import('./AdminListingCreate.vue'),
        'admin-edit-listing':       () => import('./AdminListingEdit.vue'),
        'admin-verified-listings':  () => import('./AdminVerifiedListings.vue'),
        'admin-unverified-listings': () => import('./AdminUnverifiedListings.vue'),
        'admin-deactivated-listings': () => import('./AdminDeactivatedListings.vue'),
        'admin-restore-listing':    () => import('./AdminListingRestore.vue'),
        'admin-rejected-listings':  () => import('./AdminRejectedListings.vue'),
        'admin-reopen-listing':     () => import('./AdminListingReopen.vue'),
        'admin-featured-listings':  () => import('./AdminFeaturedListings.vue'),
        'field-dashboard':          () => import('./FieldDashboard.vue'),
        'field-listings':           () => import('./FieldListings.vue'),
        'field-listing-edit':       () => import('./FieldListingEdit.vue'),
        'field-request-verification': () => import('./FieldRequestVerification.vue'),
        'field-priority':           () => import('./FieldPriority.vue'),
    };

    const loadRoot = roots[page] || roots['admin-login'];

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
