<script setup>
import { nextTick, ref } from 'vue';
import DarkModeToggle from './components/DarkModeToggle.vue';

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
    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-12 bg-gray-50 dark:bg-gray-950 relative">
        <!-- Floating dark mode toggle -->
        <div class="absolute top-4 right-4">
            <DarkModeToggle />
        </div>

        <!-- Logo -->
        <a href="/" class="flex items-center gap-3 mb-6">
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

        <!-- Card -->
        <div class="w-full max-w-md bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm p-8">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Set a new password</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Choose something memorable but hard to guess.
            </p>

            <p
                v-if="errorFor('email') || errorFor('token')"
                class="mt-6 p-4 rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-sm text-red-700 dark:text-red-300"
            >
                {{ errorFor('email') || errorFor('token') }}
            </p>

            <form
                ref="formEl"
                method="POST"
                action="/reset-password"
                @submit.prevent="submit"
                class="mt-6 space-y-4"
                novalidate
            >
                <input type="hidden" name="_token" :value="csrfToken">
                <input type="hidden" name="token" :value="TOKEN">
                <button type="submit" class="hidden" tabindex="-1" aria-hidden="true"></button>

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
                    <p v-if="errorFor('password_confirmation')" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ errorFor('password_confirmation') }}</p>
                </div>

                <button
                    type="button"
                    @click="submit"
                    class="w-full px-6 py-3 rounded-lg bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium transition cursor-pointer"
                >
                    Reset password
                </button>
            </form>

            <div class="mt-6 text-center text-sm">
                <a href="/auth/login" class="font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors cursor-pointer">
                    Back to sign in
                </a>
            </div>
        </div>
    </div>
</template>
