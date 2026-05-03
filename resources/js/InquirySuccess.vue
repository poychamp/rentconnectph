<script setup>
import Navbar from './components/Navbar.vue';
import Footer from './components/Footer.vue';
import BottomNav from './components/BottomNav.vue';

const initial = window.__INITIAL_SUCCESS__ ?? { listingTitle: null };
const listingTitle = initial.listingTitle;
</script>

<template>
    <div class="min-h-screen bg-white text-gray-900 dark:bg-gray-950 dark:text-gray-100 pb-20 md:pb-0 flex flex-col">
        <div class="hidden md:block">
            <Navbar />
        </div>

        <main class="flex-1 flex items-center justify-center px-4 py-16 md:py-24">
            <div class="max-w-md w-full text-center">
                <div class="mb-6">
                    <div class="mx-auto w-20 h-20 rounded-full bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center">
                        <svg class="w-10 h-10 text-orange-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                        </svg>
                    </div>
                </div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-3">
                    Thanks — we got your inquiry
                </h1>
                <p v-if="listingTitle" class="text-gray-600 dark:text-gray-400 mb-8 leading-relaxed">
                    Our team will reach out to you shortly to chat about <span class="font-semibold text-gray-900 dark:text-white">{{ listingTitle }}</span>.
                </p>
                <p v-else class="text-gray-600 dark:text-gray-400 mb-8 leading-relaxed">
                    Our team will reach out to you shortly.
                </p>
                <a
                    href="/search"
                    class="inline-block px-6 py-3 rounded-lg bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium transition"
                >
                    Browse more listings
                </a>
            </div>
        </main>

        <div class="hidden md:block">
            <Footer />
        </div>
        <BottomNav />
    </div>
</template>
