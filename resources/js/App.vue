<script setup>
import { ref } from 'vue';
import Navbar from './components/Navbar.vue';
import HeroSection from './components/HeroSection.vue';
import ListingTypeFilter from './components/ListingTypeFilter.vue';
import ListingsSection from './components/ListingsSection.vue';
import Footer from './components/Footer.vue';
import BottomNav from './components/BottomNav.vue';

const home = window.__INITIAL_HOME__ || { verified: [], recently: [], listingTypes: [], barangays: [] };
const verified = ref(home.verified);
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
            <ListingsSection
                title="Verified Listings"
                :subtitle-desktop="'Hand-picked by RentConnectPH this week'"
                :subtitle-mobile="'Hand-picked by RentConnectPH'"
                :listings="verified"
            />
            <hr class="border-gray-200 dark:border-gray-800" />
            <ListingsSection
                title="Recently Verified"
                :subtitle-desktop="'Fresh listings, all ground-checked within the last week'"
                :subtitle-mobile="'Fresh listings, all ground-checked'"
                :listings="recently"
                show-view-all
            />
        </main>
        <Footer />
        <BottomNav />
    </div>
</template>
