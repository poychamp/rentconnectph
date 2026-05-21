<script setup>
import { nextTick, ref } from 'vue';
import DarkModeToggle from './components/DarkModeToggle.vue';

const initial = window.__INITIAL_FORGOT_PASSWORD__ ?? { errors: null, oldInput: null, success: null };
const serverErrors = initial.errors ?? {};
const oldInput     = initial.oldInput ?? {};
const flashSuccess = initial.success ?? null;

const CLIENT_VALIDATION_ENABLED = true;

const AMBIGUOUS_MESSAGE = "If an account with that email exists, we've sent a reset link.";

const form = ref({
    email: oldInput.email ?? '',
});

const clientErrors = ref({});

function errorFor(field) {
    return serverErrors[field]?.[0] ?? clientErrors.value[field] ?? null;
}

const VALIDATORS = {
    email: (v) => {
        const t = (v ?? '').trim();
        if (!t) return AMBIGUOUS_MESSAGE + '.';
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(t)) return AMBIGUOUS_MESSAGE + '..';
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

const formEl   = ref(null);
const emailRef = ref(null);
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

function submit() {
    if (!validateAll()) {
        nextTick(() => emailRef.value?.focus());
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
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Forgot your password?</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Enter your email and we'll send you a reset link.
            </p>

            <div
                v-if="flashSuccess"
                class="mt-6 p-4 rounded-md bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-sm text-emerald-700 dark:text-emerald-300"
            >
                {{ flashSuccess }}
            </div>

            <form
                v-if="!flashSuccess"
                ref="formEl"
                method="POST"
                action="/forgot-password"
                @submit.prevent="submit"
                class="mt-6 space-y-4"
                novalidate
            >
                <input type="hidden" name="_token" :value="csrfToken">
                <button type="submit" class="hidden" tabindex="-1" aria-hidden="true"></button>

                <div>
                    <label for="email" class="block text-sm font-medium mb-1">Email</label>
                    <input
                        id="email" ref="emailRef" name="email" type="text"
                        v-model="form.email"
                        @blur="validateField('email')"
                        @focus="clearFieldError('email')"
                        inputmode="email"
                        autocomplete="email"
                        class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-orange-500"
                    >
                    <p v-if="errorFor('email')" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ errorFor('email') }}</p>
                </div>

                <button
                    type="button"
                    @click="submit"
                    class="w-full px-6 py-3 rounded-lg bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium transition cursor-pointer"
                >
                    Send reset link
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
