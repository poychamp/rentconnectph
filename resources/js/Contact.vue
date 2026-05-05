<script setup>
import Navbar from './components/Navbar.vue';
import Footer from './components/Footer.vue';
import BottomNav from './components/BottomNav.vue';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const initial = window.__INITIAL_CONTACT__ ?? { errors: null, oldInput: null };
const errors  = initial.errors ?? {};
const old     = initial.oldInput ?? {};

function errorFor(field) {
    return errors[field]?.[0] ?? null;
}
</script>

<template>
    <div class="min-h-screen bg-white text-gray-900 dark:bg-gray-950 dark:text-gray-100 pb-20 md:pb-0 flex flex-col">
        <Navbar />

        <main class="flex-1 px-4 py-8 md:py-16">
            <div class="max-w-md mx-auto">
                <div class="text-center mb-6">
                    <div class="mx-auto w-16 h-16 rounded-full bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-orange-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="4" width="20" height="16" rx="2"/>
                            <path d="m22 7-10 7L2 7"/>
                        </svg>
                    </div>
                    <h1 class="text-3xl font-bold mb-2">Contact us</h1>
                    <p class="text-gray-600 dark:text-gray-400">
                        Got a question, want to list a property, or just want to say hi? Send us a note.
                    </p>
                </div>

                <form method="POST" action="/contact">
                    <input type="hidden" name="_token" :value="csrfToken">

                    <!-- Hidden submit for Enter-to-submit reliability. -->
                    <button type="submit" class="hidden" tabindex="-1" aria-hidden="true"></button>

                    <div class="space-y-4">
                        <div>
                            <label for="name" class="flex items-center gap-1.5 text-sm font-medium mb-1">
                                <svg class="w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                                Name
                            </label>
                            <input
                                id="name" name="name" type="text" autocomplete="name"
                                :value="old.name ?? ''"
                                class="w-full bg-gray-50 dark:bg-gray-800 border rounded-md px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-orange-500"
                                :class="errorFor('name') ? 'border-red-400 dark:border-red-500' : 'border-gray-200 dark:border-gray-700'"
                            >
                            <p v-if="errorFor('name')" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ errorFor('name') }}</p>
                        </div>

                        <div>
                            <label for="email" class="flex items-center gap-1.5 text-sm font-medium mb-1">
                                <svg class="w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="2" y="4" width="20" height="16" rx="2"/>
                                    <path d="m22 7-10 7L2 7"/>
                                </svg>
                                Email
                            </label>
                            <input
                                id="email" name="email" type="email" autocomplete="email"
                                :value="old.email ?? ''"
                                class="w-full bg-gray-50 dark:bg-gray-800 border rounded-md px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-orange-500"
                                :class="errorFor('email') ? 'border-red-400 dark:border-red-500' : 'border-gray-200 dark:border-gray-700'"
                            >
                            <p v-if="errorFor('email')" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ errorFor('email') }}</p>
                        </div>

                        <div>
                            <label for="message" class="flex items-center gap-1.5 text-sm font-medium mb-1">
                                <svg class="w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                </svg>
                                Message
                            </label>
                            <textarea
                                id="message" name="message" rows="6"
                                class="w-full bg-gray-50 dark:bg-gray-800 border rounded-md px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-orange-500 resize-y"
                                :class="errorFor('message') ? 'border-red-400 dark:border-red-500' : 'border-gray-200 dark:border-gray-700'"
                            >{{ old.message ?? '' }}</textarea>
                            <p v-if="errorFor('message')" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ errorFor('message') }}</p>
                        </div>

                        <button
                            type="submit"
                            class="w-full inline-flex items-center justify-center gap-2 px-6 py-3 rounded-lg bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium transition cursor-pointer"
                        >
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 2 11 13 M22 2l-7 20-4-9-9-4 20-7z" />
                            </svg>
                            Send message
                        </button>
                    </div>
                </form>
            </div>
        </main>

        <Footer />
        <BottomNav />
    </div>
</template>
