<script setup>
import { ref } from 'vue';
import Navbar from './components/Navbar.vue';
import Footer from './components/Footer.vue';
import ListingDetailMobileHeader from './components/ListingDetailMobileHeader.vue';
import ListingDetailHero from './components/ListingDetailHero.vue';
import ListingDetailMeta from './components/ListingDetailMeta.vue';
import ListingDetailDescription from './components/ListingDetailDescription.vue';
import ListingDetailAmenities from './components/ListingDetailAmenities.vue';
import ListingDetailLocation from './components/ListingDetailLocation.vue';
import BottomNav from './components/BottomNav.vue';

const listing = ref(window.__INITIAL_LISTING__);

function goBack() {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        window.location.href = '/';
    }
}
</script>

<template>
    <div class="min-h-screen bg-white text-gray-900 dark:bg-gray-950 dark:text-gray-100 pb-20 md:pb-0">
        <div class="hidden md:block">
            <Navbar />
        </div>
        <div class="md:hidden">
            <ListingDetailMobileHeader @back="goBack" />
        </div>

        <main class="max-w-7xl mx-auto px-4 md:px-6 lg:px-8 mt-4 md:mt-8">
            <a
                href="#"
                @click.prevent="goBack"
                class="hidden md:inline-flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 mb-4"
            >
                ← Back to listings
            </a>

            <div class="grid md:grid-cols-3 gap-6 lg:gap-10">
                <div class="md:col-span-2 space-y-8">
                    <ListingDetailHero :listing="listing" />
                    <ListingDetailDescription :listing="listing" />
                    <ListingDetailAmenities :listing="listing" />
                    <ListingDetailLocation :listing="listing" />
                </div>
                <aside class="md:col-span-1">
                    <ListingDetailMeta :listing="listing" class="md:sticky md:top-24" />
                </aside>
            </div>
        </main>

        <div class="hidden md:block">
            <Footer />
        </div>
        <BottomNav />
    </div>
</template>
