<script setup>
import { ref, computed } from 'vue';
import FieldSidebar from './components/field/FieldSidebar.vue';
import FieldTopBar  from './components/field/FieldTopBar.vue';
import FieldPreviewListingPhotoGallery     from './components/field/FieldPreviewListingPhotoGallery.vue';
import FieldPreviewListingDetailsCard      from './components/field/FieldPreviewListingDetailsCard.vue';
import FieldPreviewListingLocationCard     from './components/field/FieldPreviewListingLocationCard.vue';
import FieldPreviewListingAmenitiesCard    from './components/field/FieldPreviewListingAmenitiesCard.vue';
import FieldPreviewListingDescriptionCard  from './components/field/FieldPreviewListingDescriptionCard.vue';
import FieldPreviewListingCallsContextCard from './components/field/FieldPreviewListingCallsContextCard.vue';
import FieldPreviewListingBar              from './components/field/FieldPreviewListingBar.vue';
import FieldPreviewListingVisitedBanner    from './components/field/FieldPreviewListingVisitedBanner.vue';

const initial = window.__INITIAL_FIELD_LISTING_PREVIEW__ ?? {};

const user = ref(initial.user ?? {
    name:        'Field Officer',
    initials:    'FO',
    role_label:  'Field Officer',
    permissions: [],
});

const listing         = initial.listing         ?? {};
const photos          = initial.photos          ?? [];
const amenities       = initial.amenities       ?? [];
const mapboxStaticUrl = initial.mapboxStaticUrl ?? null;
const from            = initial.from            ?? 'submitted';

const subtitle = computed(() => listing.is_verified
    ? 'Read-only — verified by admin'
    : 'Read-only — awaiting admin review');
</script>

<template>
    <div class="h-screen flex overflow-hidden bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100">
        <FieldSidebar :user="user" />

        <div class="flex-1 flex flex-col min-w-0">
            <FieldTopBar title="Preview Listing" :subtitle="subtitle" />

            <main class="flex-1 overflow-y-auto p-6 lg:p-8 space-y-4">
                <FieldPreviewListingVisitedBanner :listing="listing" />

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                    <div class="lg:col-span-7 space-y-4">
                        <FieldPreviewListingPhotoGallery :photos="photos" />
                        <FieldPreviewListingDescriptionCard :description="listing.description" />
                    </div>
                    <div class="lg:col-span-5 space-y-4">
                        <FieldPreviewListingDetailsCard      :listing="listing" />
                        <FieldPreviewListingCallsContextCard :listing="listing" />
                        <FieldPreviewListingLocationCard     :listing="listing" :mapbox-url="mapboxStaticUrl" />
                        <FieldPreviewListingAmenitiesCard    :amenities="amenities" />
                    </div>
                </div>
            </main>

            <FieldPreviewListingBar :from="from" />
        </div>
    </div>
</template>
