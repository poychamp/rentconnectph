<script setup>
import Navbar from './components/Navbar.vue';
import Footer from './components/Footer.vue';
import BottomNav from './components/BottomNav.vue';
</script>

<template>
    <div class="min-h-screen bg-white text-gray-900 dark:bg-gray-950 dark:text-gray-100 pb-20 md:pb-0 flex flex-col">
        <Navbar />

        <main class="flex-1 flex items-center justify-center px-4 py-16">
            <div class="max-w-md w-full text-center">
                <div class="mb-6">
                    <div class="mx-auto w-20 h-20 rounded-full bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center">
                        <svg class="w-10 h-10 text-orange-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 2 11 13 M22 2l-7 20-4-9-9-4 20-7z" />
                        </svg>
                    </div>
                </div>

                <h1 class="text-3xl md:text-4xl font-bold text-gray-900 dark:text-white mb-4">
                    Message on its way
                </h1>
                <p class="text-sm md:text-base text-gray-600 dark:text-gray-300 mb-8 leading-relaxed">
                    Thanks for reaching out — we'll get back to you when we can.
                </p>

                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <a
                        href="/"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-1 bg-transparent hover:bg-gray-100 dark:hover:bg-gray-800 border border-gray-600/50 dark:border-gray-300/50 text-gray-600 dark:text-gray-300 text-sm font-semibold py-3 px-5 rounded-xl transition-colors cursor-pointer"
                    >
                        Back to home
                    </a>
                    <a
                        href="/search"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-1 bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white text-sm font-medium px-6 py-3 rounded-xl transition-colors cursor-pointer"
                    >
                        Browse listings
                    </a>
                </div>
            </div>
        </main>

        <Footer />
        <BottomNav />
    </div>
</template>
