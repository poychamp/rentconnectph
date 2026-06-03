<script setup>
import { nextTick, ref } from 'vue';
import Navbar from './components/Navbar.vue';
import Footer from './components/Footer.vue';
import BottomNav from './components/BottomNav.vue';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const initial = window.__INITIAL_CONTACT__ ?? { errors: null, oldInput: null };
const old     = initial.oldInput ?? {};

const CLIENT_VALIDATION_ENABLED = true;

// Form state — initialized from oldInput so failed-submit retries retain values.
const form = ref({
    name:    old.name    ?? '',
    email:   old.email   ?? '',
    message: old.message ?? '',
});

// Unified error ref. Server errors from __INITIAL_CONTACT__ are merged in at
// init so there's one source of truth from then on. clearFieldError wipes
// either kind on focus.
const fieldErrors = ref({});
if (initial.errors) {
    for (const [field, messages] of Object.entries(initial.errors)) {
        fieldErrors.value[field] = Array.isArray(messages) ? messages[0] : messages;
    }
}

const VALIDATORS = {
    name: (v) => {
        const t = (v ?? '').trim();
        if (!t) return 'Name is required.';
        if (t.length > 120) return 'Name must be 120 characters or fewer.';
        return null;
    },
    email: (v) => {
        const t = (v ?? '').trim();
        if (!t) return 'Email is required.';
        if (t.length > 255) return 'Email must be 255 characters or fewer.';
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(t)) return 'Please enter a valid email address.';
        return null;
    },
    message: (v) => {
        const t = (v ?? '').trim();
        if (!t) return 'Message is required.';
        if (t.length > 5000) return 'Message must be 5000 characters or fewer.';
        return null;
    },
};

function errorFor(field) {
    return fieldErrors.value[field] ?? null;
}

function validateField(field) {
    if (!CLIENT_VALIDATION_ENABLED) return;
    const err = VALIDATORS[field]?.(form.value[field]);
    if (err) fieldErrors.value[field] = err;
    else delete fieldErrors.value[field];
}

function clearFieldError(field) {
    delete fieldErrors.value[field];
}

function validateAll() {
    if (!CLIENT_VALIDATION_ENABLED) return true;
    const next = {};
    for (const f of Object.keys(VALIDATORS)) {
        const err = VALIDATORS[f](form.value[f]);
        if (err) next[f] = err;
    }
    fieldErrors.value = next;
    return Object.keys(next).length === 0;
}

const formEl     = ref(null);
const nameRef    = ref(null);
const emailRef   = ref(null);
const messageRef = ref(null);

const fieldRefs = { name: nameRef, email: emailRef, message: messageRef };

function focusFirstInvalid() {
    for (const f of Object.keys(VALIDATORS)) {
        if (fieldErrors.value[f]) {
            nextTick(() => fieldRefs[f].value?.focus());
            return;
        }
    }
}

function onSubmit() {
    if (!validateAll()) {
        focusFirstInvalid();
        return;
    }
    formEl.value?.submit();
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

                <form
                    ref="formEl"
                    method="POST"
                    action="/contact"
                    @submit.prevent="onSubmit"
                    novalidate
                >
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
                                id="name" ref="nameRef" name="name" type="text" autocomplete="name"
                                v-model="form.name"
                                @blur="validateField('name')"
                                @focus="clearFieldError('name')"
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
                                id="email" ref="emailRef" name="email" type="email" autocomplete="email"
                                v-model="form.email"
                                @blur="validateField('email')"
                                @focus="clearFieldError('email')"
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
                                id="message" ref="messageRef" name="message" rows="6"
                                v-model="form.message"
                                @blur="validateField('message')"
                                @focus="clearFieldError('message')"
                                class="w-full bg-gray-50 dark:bg-gray-800 border rounded-md px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-orange-500 resize-y"
                                :class="errorFor('message') ? 'border-red-400 dark:border-red-500' : 'border-gray-200 dark:border-gray-700'"
                            ></textarea>
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
