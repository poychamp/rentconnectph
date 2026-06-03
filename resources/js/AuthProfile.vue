<script setup>
import { reactive, ref, onMounted, nextTick } from 'vue';
import AdminDarkModeToggle from './components/admin/AdminDarkModeToggle.vue';

const initial = window.__INITIAL_AUTH_PROFILE__ ?? {};
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const CLIENT_VALIDATION_ENABLED = true;

// --- Card 1: Your Name ---
const nameForm = reactive({
    name: initial.oldInput?.name ?? initial.name ?? '',
});
const nameErrors = reactive({});

const NAME_VALIDATORS = {
    name: (v) => {
        if (!v || !v.trim()) return 'Please enter your name.';
        if (v.length > 255) return 'Name must be 255 characters or fewer.';
        return null;
    },
};

function validateNameField(field) {
    if (!CLIENT_VALIDATION_ENABLED) return;
    const msg = NAME_VALIDATORS[field]?.(nameForm[field]);
    if (msg) nameErrors[field] = msg;
    else delete nameErrors[field];
}

function clearNameError(field) {
    delete nameErrors[field];
}

function validateAllName() {
    if (!CLIENT_VALIDATION_ENABLED) return true;
    let ok = true;
    Object.keys(NAME_VALIDATORS).forEach((field) => {
        const msg = NAME_VALIDATORS[field](nameForm[field]);
        if (msg) {
            nameErrors[field] = msg;
            ok = false;
        } else {
            delete nameErrors[field];
        }
    });
    return ok;
}

function focusFirstInvalidName() {
    const first = Object.keys(nameErrors)[0];
    if (first) document.getElementById(first)?.focus();
}

function onSubmitName(e) {
    if (CLIENT_VALIDATION_ENABLED && !validateAllName()) {
        e.preventDefault();
        nextTick(focusFirstInvalidName);
    }
}

// --- Card 2: Change Password ---
const passwordForm = reactive({
    current_password: '',
    password: '',
    password_confirmation: '',
});
const passwordErrors = reactive({});
const showPassword = ref(false);

const PASSWORD_VALIDATORS = {
    current_password: (v) => (v && v.length ? null : 'Please enter your current password.'),
    password: (v) => {
        if (!v || !v.length) return 'Please enter a new password.';
        if (v.length < 8) return 'New password must be at least 8 characters.';
        if (v.length > 255) return 'New password must be 255 characters or fewer.';
        return null;
    },
    password_confirmation: (v) =>
        v === passwordForm.password ? null : 'New password confirmation does not match.',
};

function validatePasswordField(field) {
    if (!CLIENT_VALIDATION_ENABLED) return;
    const msg = PASSWORD_VALIDATORS[field]?.(passwordForm[field]);
    if (msg) passwordErrors[field] = msg;
    else delete passwordErrors[field];
}

function clearPasswordError(field) {
    delete passwordErrors[field];
}

function validateAllPassword() {
    if (!CLIENT_VALIDATION_ENABLED) return true;
    let ok = true;
    Object.keys(PASSWORD_VALIDATORS).forEach((field) => {
        const msg = PASSWORD_VALIDATORS[field](passwordForm[field]);
        if (msg) {
            passwordErrors[field] = msg;
            ok = false;
        } else {
            delete passwordErrors[field];
        }
    });
    return ok;
}

function focusFirstInvalidPassword() {
    const first = Object.keys(passwordErrors)[0];
    if (first) document.getElementById(first)?.focus();
}

function onSubmitPassword(e) {
    if (CLIENT_VALIDATION_ENABLED && !validateAllPassword()) {
        e.preventDefault();
        nextTick(focusFirstInvalidPassword);
    }
}

// --- Server-error hydration (per-bag) ---
onMounted(() => {
    if (initial.errorsUpdateName) {
        Object.entries(initial.errorsUpdateName).forEach(([k, msgs]) => {
            nameErrors[k] = Array.isArray(msgs) ? msgs[0] : msgs;
        });
    }
    if (initial.errorsUpdatePassword) {
        Object.entries(initial.errorsUpdatePassword).forEach(([k, msgs]) => {
            passwordErrors[k] = Array.isArray(msgs) ? msgs[0] : msgs;
        });
    }
});
</script>

<template>
    <div class="min-h-screen bg-gray-50 dark:bg-gray-950">
        <!-- Top bar -->
        <header class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between">
                <a
                    :href="initial.dashboardUrl ?? '/admin'"
                    class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition"
                >
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 12H5M12 19l-7-7 7-7" />
                    </svg>
                    Back to dashboard
                </a>
                <AdminDarkModeToggle />
            </div>
        </header>

        <!-- Page -->
        <main class="max-w-3xl mx-auto px-4 sm:px-6 py-8 space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Profile</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Manage your account details and credentials.
                </p>
            </div>

            <!-- Card 1: Your Name -->
            <section class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm p-6 sm:p-8">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Your Name</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    This is how your name appears throughout the app.
                </p>

                <form
                    action="/auth/profile/name"
                    method="POST"
                    @submit="onSubmitName"
                    class="mt-6 space-y-4"
                >
                    <input type="hidden" name="_token" :value="csrfToken">
                    <input type="hidden" name="_method" value="PUT">

                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Name
                        </label>
                        <input
                            id="name"
                            type="text"
                            name="name"
                            v-model="nameForm.name"
                            @blur="validateNameField('name')"
                            @focus="clearNameError('name')"
                            autocomplete="name"
                            :class="[
                                'mt-1 w-full rounded-md border px-3.5 py-2.5 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition',
                                nameErrors.name ? 'border-red-500' : 'border-gray-200 dark:border-gray-700',
                            ]"
                        >
                        <p v-if="nameErrors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ nameErrors.name }}
                        </p>
                    </div>

                    <button type="submit" class="hidden" tabindex="-1" aria-hidden="true"></button>

                    <div class="flex justify-end pt-2">
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-md bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-orange-600 transition cursor-pointer"
                        >
                            Save name
                        </button>
                    </div>
                </form>
            </section>

            <!-- Card 2: Change Password -->
            <section class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm p-6 sm:p-8">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Change Password</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Updating your password signs you out of all other devices.
                </p>

                <form
                    action="/auth/profile/password"
                    method="POST"
                    @submit="onSubmitPassword"
                    class="mt-6 space-y-4"
                >
                    <input type="hidden" name="_token" :value="csrfToken">
                    <input type="hidden" name="_method" value="PUT">

                    <!-- Current password -->
                    <div>
                        <label for="current_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Current password
                        </label>
                        <div class="relative mt-1">
                            <input
                                id="current_password"
                                :type="showPassword ? 'text' : 'password'"
                                name="current_password"
                                v-model="passwordForm.current_password"
                                @blur="validatePasswordField('current_password')"
                                @focus="clearPasswordError('current_password')"
                                autocomplete="current-password"
                                :class="[
                                    'w-full rounded-md border px-3.5 py-2.5 pr-11 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition',
                                    passwordErrors.current_password ? 'border-red-500' : 'border-gray-200 dark:border-gray-700',
                                ]"
                            >
                            <button
                                type="button"
                                tabindex="-1"
                                @click="showPassword = !showPassword"
                                :title="showPassword ? 'Hide password' : 'Show password'"
                                class="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 rounded-md text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition"
                            >
                                <svg v-if="showPassword" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24M1 1l22 22"/>
                                </svg>
                                <svg v-else class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/>
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                            </button>
                        </div>
                        <p v-if="passwordErrors.current_password" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ passwordErrors.current_password }}
                        </p>
                    </div>

                    <!-- New password -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            New password
                        </label>
                        <div class="relative mt-1">
                            <input
                                id="password"
                                :type="showPassword ? 'text' : 'password'"
                                name="password"
                                v-model="passwordForm.password"
                                @blur="validatePasswordField('password')"
                                @focus="clearPasswordError('password')"
                                autocomplete="new-password"
                                :class="[
                                    'w-full rounded-md border px-3.5 py-2.5 pr-11 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition',
                                    passwordErrors.password ? 'border-red-500' : 'border-gray-200 dark:border-gray-700',
                                ]"
                            >
                            <button
                                type="button"
                                tabindex="-1"
                                @click="showPassword = !showPassword"
                                :title="showPassword ? 'Hide password' : 'Show password'"
                                class="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 rounded-md text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition"
                            >
                                <svg v-if="showPassword" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24M1 1l22 22"/>
                                </svg>
                                <svg v-else class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/>
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                            </button>
                        </div>
                        <p v-if="passwordErrors.password" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ passwordErrors.password }}
                        </p>
                        <p v-else class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            At least 8 characters.
                        </p>
                    </div>

                    <!-- Confirm new password -->
                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Confirm new password
                        </label>
                        <div class="relative mt-1">
                            <input
                                id="password_confirmation"
                                :type="showPassword ? 'text' : 'password'"
                                name="password_confirmation"
                                v-model="passwordForm.password_confirmation"
                                @blur="validatePasswordField('password_confirmation')"
                                @focus="clearPasswordError('password_confirmation')"
                                autocomplete="new-password"
                                :class="[
                                    'w-full rounded-md border px-3.5 py-2.5 pr-11 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition',
                                    passwordErrors.password_confirmation ? 'border-red-500' : 'border-gray-200 dark:border-gray-700',
                                ]"
                            >
                            <button
                                type="button"
                                tabindex="-1"
                                @click="showPassword = !showPassword"
                                :title="showPassword ? 'Hide password' : 'Show password'"
                                class="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 rounded-md text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition"
                            >
                                <svg v-if="showPassword" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24M1 1l22 22"/>
                                </svg>
                                <svg v-else class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/>
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                            </button>
                        </div>
                        <p v-if="passwordErrors.password_confirmation" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ passwordErrors.password_confirmation }}
                        </p>
                    </div>

                    <button type="submit" class="hidden" tabindex="-1" aria-hidden="true"></button>

                    <div class="flex justify-end pt-2">
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-md bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-orange-600 transition cursor-pointer"
                        >
                            Update password
                        </button>
                    </div>
                </form>
            </section>
        </main>
    </div>
</template>
