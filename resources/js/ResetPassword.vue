<script setup>
import { ref } from 'vue';
import Navbar from './components/Navbar.vue';
import Footer from './components/Footer.vue';
import BottomNav from './components/BottomNav.vue';

const initial = window.__INITIAL_RESET_PASSWORD__ ?? { errors: null, oldInput: null, token: '', email: '' };
const serverErrors = initial.errors ?? {};

const TOKEN = initial.token ?? '';
const EMAIL = initial.email ?? '';

function errorFor(field) {
    return serverErrors[field]?.[0] ?? null;
}

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
</script>

<template>
    <div class="min-h-screen bg-white dark:bg-gray-950 text-gray-900 dark:text-gray-100 pb-20 md:pb-0 flex flex-col">
        <Navbar />

        <main class="flex-1 px-4 py-8 md:py-16">
            <div class="max-w-md mx-auto">
                <div class="text-center mb-8">
                    <div class="mx-auto w-16 h-16 rounded-full bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-orange-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                            <circle cx="12" cy="16" r="1" />
                            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                        </svg>
                    </div>
                    <h1 class="text-2xl font-bold mb-2">Set a new password</h1>
                    <p class="text-gray-600 dark:text-gray-400 text-sm">
                        Choose something memorable but hard to guess.
                    </p>
                </div>

                <p
                    v-if="errorFor('email')"
                    class="mb-6 p-4 rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-sm text-red-700 dark:text-red-300"
                >
                    {{ errorFor('email') }}
                </p>

                <form
                    method="POST"
                    action="/reset-password"
                >
                    <input type="hidden" name="_token" :value="csrfToken">
                    <input type="hidden" name="token" :value="TOKEN">

                    <div class="space-y-4">
                        <div>
                            <label for="email" class="block text-sm font-medium mb-1">Email</label>
                            <input
                                id="email" name="email" type="text"
                                :value="EMAIL"
                                readonly
                                class="w-full bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-md px-3 py-2 text-sm text-gray-600 dark:text-gray-400 outline-none cursor-not-allowed"
                            >
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium mb-1">New password</label>
                            <input
                                id="password" name="password" type="password"
                                autocomplete="new-password"
                                class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-orange-500"
                            >
                            <p v-if="errorFor('password')" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ errorFor('password') }}</p>
                            <p v-else class="mt-1 text-xs text-gray-500 dark:text-gray-400">At least 8 characters.</p>
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium mb-1">Confirm new password</label>
                            <input
                                id="password_confirmation" name="password_confirmation" type="password"
                                autocomplete="new-password"
                                class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-orange-500"
                            >
                        </div>

                        <button
                            type="submit"
                            class="w-full px-6 py-3 rounded-lg bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium transition cursor-pointer"
                        >
                            Reset password
                        </button>
                    </div>
                </form>

                <div class="mt-6 text-center text-sm">
                    <a href="/auth/login" class="text-orange-600 dark:text-orange-400 hover:underline">
                        Back to sign in
                    </a>
                </div>
            </div>
        </main>

        <Footer />
        <BottomNav />
    </div>
</template>
