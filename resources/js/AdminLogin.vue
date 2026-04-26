<script setup>
import { ref } from 'vue';
import AdminLogo from './components/admin/AdminLogo.vue';
import AdminDarkModeToggle from './components/admin/AdminDarkModeToggle.vue';

const showPassword = ref(false);
</script>

<template>
    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-12 bg-gray-50 dark:bg-gray-950 relative">
        <!-- Floating dark mode toggle -->
        <div class="absolute top-4 right-4">
            <AdminDarkModeToggle />
        </div>

        <!-- Header -->
        <div class="flex flex-col items-center mb-6">
            <AdminLogo size="lg" />
            <span class="mt-3 text-xs uppercase tracking-widest text-gray-500 dark:text-gray-400">
                Admin Central
            </span>
        </div>

        <!-- Card -->
        <div class="w-full max-w-md bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm p-8">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Welcome back</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Sign in to your account to continue.</p>

            <form class="mt-6 space-y-4">
                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Email
                    </label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        placeholder="you@rentconnect.ph"
                        autocomplete="email"
                        class="mt-1 w-full rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3.5 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition"
                    >
                </div>

                <!-- Password -->
                <div>
                    <div class="flex justify-between items-center">
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Password
                        </label>
                        <a href="#" class="text-sm text-orange-500 hover:text-orange-600">Forgot password?</a>
                    </div>
                    <div class="relative mt-1">
                        <input
                            id="password"
                            :type="showPassword ? 'text' : 'password'"
                            name="password"
                            autocomplete="current-password"
                            class="w-full rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3.5 py-2.5 pr-11 text-sm text-gray-900 dark:text-white focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition"
                        >
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                            tabindex="-1"
                            :title="showPassword ? 'Hide password' : 'Show password'"
                        >
                            <svg v-if="showPassword" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24M1 1l22 22"/>
                            </svg>
                            <svg v-else class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Keep me signed in -->
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                    <input
                        type="checkbox"
                        name="remember"
                        checked
                        class="rounded border-gray-300 text-orange-500 focus:ring-orange-500"
                    >
                    Keep me signed in for 30 days
                </label>

                <!-- Sign In -->
                <button
                    type="submit"
                    class="w-full rounded-md bg-orange-500 hover:bg-orange-600 text-white font-medium py-2.5 transition"
                >
                    Sign In
                </button>
            </form>
        </div>

        <!-- Version stamp -->
        <p class="mt-6 text-xs text-gray-400 dark:text-gray-500">v0.4.2 · build 2026.04.26</p>
    </div>
</template>
