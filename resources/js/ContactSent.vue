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

                <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-3">
                    Message on its way
                </h1>
                <p class="text-gray-600 dark:text-gray-400 mb-8 leading-relaxed">
                    Thanks for reaching out — we'll get back to you when we can.
                </p>

                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <a
                        href="/"
                        class="inline-block px-6 py-3 rounded-lg border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 text-sm font-medium transition"
                    >
                        Back to home
                    </a>
                    <a
                        href="/search"
                        class="inline-block px-6 py-3 rounded-lg bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium transition"
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
