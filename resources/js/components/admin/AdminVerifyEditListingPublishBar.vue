<script setup>
import { ref, computed, inject, toRef } from 'vue';

const form = inject('addListingForm');
const featureOnHomepage = toRef(form, 'is_featured');

const { validateAll } = inject('addListingFormValidate', { validateAll: () => true });
const scrollFormToTop = inject('scrollFormToTop', () => {});

const submitting = ref(false);

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const listingUuid = window.__INITIAL_VERIFY_EDIT_LISTING__?.listing?.uuid ?? '';
const formAction = computed(() => `/admin/listings/${listingUuid}/verify`);

function appendHidden(formEl, name, value) {
    if (value === null || value === undefined) return;

    if (Array.isArray(value)) {
        value.forEach((item, i) => appendHidden(formEl, `${name}[${i}]`, item));
        return;
    }

    if (typeof value === 'object') {
        Object.entries(value).forEach(([k, v]) => appendHidden(formEl, `${name}[${k}]`, v));
        return;
    }

    const input = document.createElement('input');
    input.type  = 'hidden';
    input.name  = name;
    input.value = typeof value === 'boolean' ? (value ? '1' : '0') : String(value);
    formEl.appendChild(input);
}

function verify() {
    if (submitting.value) return;

    if (!validateAll()) {
        scrollFormToTop();
        return;
    }

    submitting.value = true;

    const formEl = document.createElement('form');
    formEl.method = 'POST';
    formEl.action = formAction.value;
    formEl.style.display = 'none';

    appendHidden(formEl, '_token', csrfToken);
    appendHidden(formEl, '_method', 'PUT');

    Object.entries(form).forEach(([key, value]) => {
        appendHidden(formEl, key, value);
    });

    document.body.appendChild(formEl);
    formEl.submit();
}
</script>

<template>
    <div class="shrink-0 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800 px-6 py-3 flex items-center gap-4">
        <a
            href="/admin/visited-listings"
            class="px-4 py-2 rounded-md text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
        >
            Cancel
        </a>

        <div class="flex-1"></div>

        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
            <input
                type="checkbox"
                v-model="featureOnHomepage"
                class="rounded border-gray-300 text-orange-500 focus:ring-orange-500"
            >
            <svg class="w-4 h-4 text-gray-400 dark:text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
            </svg>
            Feature on homepage
        </label>

        <button
            type="button"
            :disabled="submitting"
            @click="verify"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white text-sm font-medium transition cursor-pointer disabled:opacity-90 disabled:cursor-not-allowed"
        >
            <svg v-if="submitting" class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-opacity="0.25"/>
                <path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
            </svg>
            <svg v-else class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                <polyline points="22 4 12 14.01 9 11.01" />
            </svg>
            {{ submitting ? 'Verifying…' : 'Verify Listing' }}
        </button>
    </div>
</template>
