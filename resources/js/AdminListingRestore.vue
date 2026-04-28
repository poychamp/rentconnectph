<script setup>
import { ref } from 'vue';
import AdminSidebar from './components/admin/AdminSidebar.vue';
import AdminTopBar  from './components/admin/AdminTopBar.vue';
import AdminRestoreListingDeactivationBanner from './components/admin/AdminRestoreListingDeactivationBanner.vue';
import AdminRestoreListingPhotoGallery        from './components/admin/AdminRestoreListingPhotoGallery.vue';
import AdminRestoreListingDetailsCard         from './components/admin/AdminRestoreListingDetailsCard.vue';
import AdminRestoreListingLocationCard        from './components/admin/AdminRestoreListingLocationCard.vue';
import AdminRestoreListingAmenitiesCard       from './components/admin/AdminRestoreListingAmenitiesCard.vue';
import AdminRestoreListingDescriptionCard     from './components/admin/AdminRestoreListingDescriptionCard.vue';
import AdminRestoreListingBar                 from './components/admin/AdminRestoreListingBar.vue';

const initial = window.__INITIAL_RESTORE_LISTING__ ?? {};

const user = ref(window.__INITIAL_DASHBOARD__?.user ?? {
    name: 'Admin', initials: 'AD', role_label: 'Super Admin',
});

const listingUuid     = initial.listingUuid     ?? '';
const listing         = initial.listing         ?? {};
const photos          = initial.photos          ?? [];
const amenities       = initial.amenities       ?? [];
const deactivation    = initial.deactivation    ?? {};
const mapboxStaticUrl = initial.mapboxStaticUrl ?? null;
const restoreErrors   = initial.restoreErrors   ?? null;
</script>

<template>
    <div class="h-screen flex overflow-hidden bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100">
        <AdminSidebar :user="user" />

        <div class="flex-1 flex flex-col min-w-0">
            <AdminTopBar title="Restore Listing" />

            <main class="flex-1 overflow-y-auto px-6 pt-2 pb-6 lg:px-10 lg:pt-3 lg:pb-10 space-y-4">
                <AdminRestoreListingDeactivationBanner :deactivation="deactivation" />

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                    <div class="lg:col-span-7">
                        <AdminRestoreListingPhotoGallery :photos="photos" />
                    </div>
                    <div class="lg:col-span-5 space-y-4">
                        <AdminRestoreListingDetailsCard     :listing="listing" />
                        <AdminRestoreListingLocationCard    :listing="listing" :mapbox-url="mapboxStaticUrl" />
                        <AdminRestoreListingAmenitiesCard   :amenities="amenities" />
                        <AdminRestoreListingDescriptionCard :description="listing.description" />
                    </div>
                </div>
            </main>

            <AdminRestoreListingBar :listing-uuid="listingUuid" :errors="restoreErrors" />
        </div>
    </div>
</template>
