<script setup>
import { ref } from 'vue';
import AdminSidebar from './components/admin/AdminSidebar.vue';
import AdminTopBar  from './components/admin/AdminTopBar.vue';
import AdminReopenListingRejectionBanner from './components/admin/AdminReopenListingRejectionBanner.vue';
import AdminReopenListingPhotoGallery    from './components/admin/AdminReopenListingPhotoGallery.vue';
import AdminReopenListingDetailsCard     from './components/admin/AdminReopenListingDetailsCard.vue';
import AdminReopenListingLocationCard    from './components/admin/AdminReopenListingLocationCard.vue';
import AdminReopenListingAmenitiesCard   from './components/admin/AdminReopenListingAmenitiesCard.vue';
import AdminReopenListingDescriptionCard from './components/admin/AdminReopenListingDescriptionCard.vue';
import AdminReopenListingCallsContextCard from './components/admin/AdminReopenListingCallsContextCard.vue';
import AdminReopenListingBar             from './components/admin/AdminReopenListingBar.vue';

const initial = window.__INITIAL_REOPEN_LISTING__ ?? {};

const user = ref(window.__INITIAL_DASHBOARD__?.user ?? {
    name: 'Admin', initials: 'AD', role_label: 'Super Admin', permissions: [],
});

const listingUuid     = initial.listingUuid     ?? '';
const listing         = initial.listing         ?? {};
const photos          = initial.photos          ?? [];
const amenities       = initial.amenities       ?? [];
const rejection       = initial.rejection       ?? {};
const mapboxStaticUrl = initial.mapboxStaticUrl ?? null;
const reopenErrors    = initial.reopenErrors    ?? null;
</script>

<template>
    <div class="h-screen flex overflow-hidden bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100">
        <AdminSidebar :user="user" />

        <div class="flex-1 flex flex-col min-w-0">
            <AdminTopBar title="Reopen Listing" />

            <main class="flex-1 overflow-y-auto p-6 lg:p-8 space-y-4">
                <AdminReopenListingRejectionBanner :rejection="rejection" />

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                    <div class="lg:col-span-7 space-y-4">
                        <AdminReopenListingPhotoGallery    :photos="photos" />
                        <AdminReopenListingDescriptionCard :description="listing.description" />
                    </div>
                    <div class="lg:col-span-5 space-y-4">
                        <AdminReopenListingDetailsCard      :listing="listing" />
                        <AdminReopenListingCallsContextCard :listing="listing" />
                        <AdminReopenListingLocationCard     :listing="listing" :mapbox-url="mapboxStaticUrl" />
                        <AdminReopenListingAmenitiesCard    :amenities="amenities" />
                    </div>
                </div>
            </main>

            <AdminReopenListingBar :listing-uuid="listingUuid" :errors="reopenErrors" />
        </div>
    </div>
</template>
