<script setup>
import { ref, reactive, onMounted, provide } from 'vue';
import AdminSidebar from './components/admin/AdminSidebar.vue';
import AdminTopBar from './components/admin/AdminTopBar.vue';
import AdminAddListingShortcutPill from './components/admin/AdminAddListingShortcutPill.vue';
import AdminAddListingFormCard from './components/admin/AdminAddListingFormCard.vue';
import AdminAddListingPublishBar from './components/admin/AdminAddListingPublishBar.vue';

const user = ref({
    name:       'Admin',
    initials:   'AD',
    role_label: 'Super Admin',
});

const formData = ref({
    listingTypes: [],
    barangays:    [],
    amenities:    [],
});

// Shared submit-payload state. Each child reads/writes its slice via inject('addListingForm').
// Photos hold Vapor S3 keys (strings) once uploaded — Phase 2 wires the actual Vapor.store() call;
// for now they're whatever the photo upload component decides to push (placeholder strings ok).
const addListingForm = reactive({
    title:              '',
    description:        '',
    listing_type:       '',
    monthly_rent:       null,
    barangay:           '',
    beds:               0,
    baths:              0,
    sqft:               0,
    latitude:           null,
    longitude:          null,
    amenities:          [],          // array of amenity IDs
    photos:             [],          // array of S3 keys (Vapor) — strings
    feature_on_homepage: false,
});

provide('addListingForm', addListingForm);

onMounted(() => {
    if (window.__INITIAL_DASHBOARD__?.user) {
        user.value = window.__INITIAL_DASHBOARD__.user;
    }
    if (window.__INITIAL_ADD_LISTING__) {
        formData.value = window.__INITIAL_ADD_LISTING__;
    }
});
</script>

<template>
    <div class="h-screen flex overflow-hidden bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100">
        <AdminSidebar :user="user" />

        <div class="flex-1 flex flex-col min-w-0">
            <AdminTopBar
                title="Admin Add Listing"
                subtitle="Direct admin entry — published & verified immediately"
            />

            <main class="flex-1 overflow-y-auto p-6 lg:p-10">
                <AdminAddListingShortcutPill />
                <h1 class="mt-3 text-3xl font-bold text-gray-900 dark:text-white tracking-tight">
                    Admin Add Listing
                </h1>
                <p class="mt-2 text-gray-500 dark:text-gray-400">
                    Direct admin entry — published &amp; verified immediately. Skips broker review.
                </p>

                <AdminAddListingFormCard
                    class="mt-6"
                    :listing-types="formData.listingTypes"
                    :barangays="formData.barangays"
                    :amenities="formData.amenities"
                />
            </main>

            <AdminAddListingPublishBar />
        </div>
    </div>
</template>
