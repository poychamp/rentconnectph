<script setup>
import { computed, onMounted, ref } from 'vue';
import AdminDarkModeToggle from './components/admin/AdminDarkModeToggle.vue';

const showPassword = ref(false);
const submitting   = ref(false);
const loginError   = ref(null);
const oldEmail     = ref('');

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

onMounted(() => {
    const initial = window.__INITIAL_LOGIN__ ?? {};
    oldEmail.value   = initial.oldEmail ?? '';
    loginError.value = initial.loginError ?? null;
});

const inputErrorClass = computed(() => loginError.value
    ? 'border-red-500 bg-red-50/60 dark:bg-red-950/30'
    : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800'
);

function onSubmit() {
    submitting.value = true;
    // No e.preventDefault — Vue just toggles loading state, the form does its native POST.
}
</script>

<template>
    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-12 bg-gray-50 dark:bg-gray-950 relative">
        <!-- Floating dark mode toggle -->
        <div class="absolute top-4 right-4">
            <AdminDarkModeToggle />
        </div>

        <!-- Header -->
        <div class="flex flex-col items-center mb-6">
            <a href="/" class="flex items-center gap-3">
                <span class="relative inline-flex items-center justify-center w-12 h-12 rounded-md bg-orange-500 text-white">
                    <svg class="w-7 h-7" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3 2 12h3v8h6v-6h2v6h6v-8h3z"/></svg>
                    <span class="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-gray-900 grid place-items-center">
                        <svg class="w-3 h-3 text-white" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/>
                        </svg>
                    </span>
                </span>
                <span class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
                    RentConnect<span class="text-orange-500">PH</span>
                </span>
            </a>
        </div>

        <!-- Card -->
        <div class="w-full max-w-md bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm p-8">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Welcome back</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Sign in to your account to continue.</p>

            <form
                action="/auth/login"
                method="POST"
                @submit="onSubmit"
                class="mt-6 space-y-4"
            >
                <input type="hidden" name="_token" :value="csrfToken">

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Email
                    </label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        :value="oldEmail"
                        @input="oldEmail = $event.target.value"
                        placeholder="you@rentconnect.ph"
                        autocomplete="email"
                        :class="['mt-1 w-full rounded-md border px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition', inputErrorClass]"
                    >
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Password
                    </label>
                    <div class="relative mt-1">
                        <input
                            id="password"
                            :type="showPassword ? 'text' : 'password'"
                            name="password"
                            autocomplete="current-password"
                            :class="['w-full rounded-md border px-3 py-2 pr-11 text-sm text-gray-900 dark:text-white focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition', inputErrorClass]"
                        >
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer"
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
                    <div class="mt-1.5 flex justify-end">
                        <a href="/forgot-password" class="text-xs text-orange-500 hover:text-orange-600">Forgot password?</a>
                    </div>
                </div>

                <!-- Error banner -->
                <div
                    v-if="loginError"
                    class="flex items-center gap-2 rounded-md bg-red-50 dark:bg-red-950/30 px-3 py-2 text-sm text-red-700 dark:text-red-400"
                >
                    <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 9v4M12 17h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/>
                    </svg>
                    <span>{{ loginError }}</span>
                </div>

                <!-- Keep me signed in -->
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                    <input
                        type="checkbox"
                        name="remember"
                        value="1"
                        checked
                        class="rounded border-gray-300 text-orange-500 focus:ring-orange-500"
                    >
                    Keep me signed in for 30 days
                </label>

                <!-- Sign In -->
                <button
                    type="submit"
                    :disabled="submitting"
                    :class="[
                        'w-full rounded-md bg-orange-500 hover:bg-orange-600 text-white font-medium py-2 transition flex items-center justify-center gap-2 cursor-pointer',
                        submitting && 'cursor-not-allowed opacity-90',
                    ]"
                >
                    <svg v-if="submitting" class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-opacity="0.25"/>
                        <path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                    </svg>
                    {{ submitting ? 'Signing in...' : 'Sign In' }}
                </button>
            </form>
        </div>

    </div>
</template>
