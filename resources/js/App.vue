<script setup>
import { ref } from 'vue';
import Navbar from './components/Navbar.vue';
import HeroSection from './components/HeroSection.vue';
import ListingTypeFilter from './components/ListingTypeFilter.vue';
import ListingsSection from './components/ListingsSection.vue';
import Footer from './components/Footer.vue';
import BottomNav from './components/BottomNav.vue';
import GetTheAppSection from './components/GetTheAppSection.vue';

const home = window.__INITIAL_HOME__ || { featured: [], recently: [], listingTypes: [], barangays: [] };
const featured = ref(home.featured);
const recently = ref(home.recently);
const listingTypes = ref(home.listingTypes);
const barangays = ref(home.barangays);
</script>

<template>
    <div class="min-h-screen bg-white text-gray-900 dark:bg-gray-950 dark:text-gray-100 pb-20 md:pb-0">
        <Navbar />
        <HeroSection :barangays="barangays" />
        <ListingTypeFilter :listing-types="listingTypes" />
        <main class="max-w-7xl mx-auto px-4 md:px-6 lg:px-8 space-y-12 mt-8">
            <template v-if="featured.length > 0">
                <ListingsSection
                    title="Featured Listings"
                    :subtitle-desktop="'Hand-picked by RentConnectPH this week'"
                    :subtitle-mobile="'Hand-picked by RentConnectPH'"
                    :listings="featured"
                />
                <hr class="border-gray-200 dark:border-gray-800" />
            </template>
            <ListingsSection
                title="Recently Verified"
                :subtitle-desktop="'Fresh listings, all ground-checked within the last week'"
                :subtitle-mobile="'Fresh listings, all ground-checked'"
                :listings="recently"
                show-view-all
            />
        </main>
        <GetTheAppSection />
        <Footer />
        <BottomNav />
    </div>
</template>
