<script setup>
import { nextTick, ref } from 'vue';
import Navbar from './components/Navbar.vue';
import Footer from './components/Footer.vue';
import BottomNav from './components/BottomNav.vue';

const initial = window.__INITIAL_RESET_PASSWORD__ ?? { errors: null, oldInput: null, token: '', email: '' };
const serverErrors = initial.errors ?? {};

const TOKEN = initial.token ?? '';
const EMAIL = initial.email ?? '';

const showPassword = ref(false);

const CLIENT_VALIDATION_ENABLED = true;

const form = ref({
    password:              '',
    password_confirmation: '',
});

const clientErrors = ref({});

function errorFor(field) {
    return serverErrors[field]?.[0] ?? clientErrors.value[field] ?? null;
}

const VALIDATORS = {
    password: (v) => {
        const t = v ?? '';
        if (!t) return 'Please choose a password.';
        if (t.length < 8) return 'Password must be at least 8 characters.';
        return null;
    },
    password_confirmation: (v) => {
        if ((v ?? '') !== form.value.password) {
            return 'Password confirmation does not match.';
        }
        return null;
    },
};

function validateField(field) {
    if (!CLIENT_VALIDATION_ENABLED) return;
    const err = VALIDATORS[field]?.(form.value[field]);
    if (err) clientErrors.value[field] = err;
    else delete clientErrors.value[field];
}

function clearFieldError(field) {
    delete clientErrors.value[field];
}

function validateAll() {
    if (!CLIENT_VALIDATION_ENABLED) return true;
    const next = {};
    for (const f of Object.keys(VALIDATORS)) {
        const err = VALIDATORS[f](form.value[f]);
        if (err) next[f] = err;
    }
    clientErrors.value = next;
    return Object.keys(next).length === 0;
}

const formEl      = ref(null);
const passwordRef = ref(null);
const csrfToken   = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

function submit() {
    if (!validateAll()) {
        const firstInvalid = Object.keys(VALIDATORS).find(f => clientErrors.value[f]);
        if (firstInvalid === 'password') nextTick(() => passwordRef.value?.focus());
        return;
    }
    formEl.value?.submit();
}
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
                    v-if="errorFor('email') || errorFor('token')"
                    class="mb-6 p-4 rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-sm text-red-700 dark:text-red-300"
                >
                    {{ errorFor('email') || errorFor('token') }}
                </p>

                <form
                    ref="formEl"
                    method="POST"
                    action="/reset-password"
                    @submit.prevent="submit"
                    novalidate
                >
                    <input type="hidden" name="_token" :value="csrfToken">
                    <input type="hidden" name="token" :value="TOKEN">
                    <button type="submit" class="hidden" tabindex="-1" aria-hidden="true"></button>

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
                            <div class="relative">
                                <input
                                    id="password" ref="passwordRef" name="password"
                                    :type="showPassword ? 'text' : 'password'"
                                    v-model="form.password"
                                    @blur="validateField('password')"
                                    @focus="clearFieldError('password')"
                                    autocomplete="new-password"
                                    class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md px-3 py-2 pr-11 text-sm outline-none focus:ring-2 focus:ring-orange-500"
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
                            <p v-if="errorFor('password')" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ errorFor('password') }}</p>
                            <p v-else class="mt-1 text-xs text-gray-500 dark:text-gray-400">At least 8 characters.</p>
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium mb-1">Confirm new password</label>
                            <div class="relative">
                                <input
                                    id="password_confirmation" name="password_confirmation"
                                    :type="showPassword ? 'text' : 'password'"
                                    v-model="form.password_confirmation"
                                    @blur="validateField('password_confirmation')"
                                    @focus="clearFieldError('password_confirmation')"
                                    autocomplete="new-password"
                                    class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md px-3 py-2 pr-11 text-sm outline-none focus:ring-2 focus:ring-orange-500"
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
                            <p v-if="errorFor('password_confirmation')" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ errorFor('password_confirmation') }}</p>
                        </div>

                        <button
                            type="button"
                            @click="submit"
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
