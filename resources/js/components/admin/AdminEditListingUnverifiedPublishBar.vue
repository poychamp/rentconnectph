<script setup>
import { ref, computed, inject } from 'vue';

const form = inject('addListingForm');
const { validateAll } = inject('addListingFormValidate', { validateAll: () => true });
const scrollFormToTop = inject('scrollFormToTop', () => {});

const submitting = ref(false);

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const listingUuid = window.__INITIAL_EDIT_LISTING__?.listingUuid ?? '';
const formAction = computed(() => `/admin/listings/${listingUuid}/unverified-update`);

// Cancel always returns to unverified-listings — the only origin page that
// links into this slice.
const cancelHref = '/admin/unverified-listings';

const isMac = computed(() => /Mac|iPod|iPhone|iPad/.test(navigator.platform));
const modKey = computed(() => isMac.value ? '⌘' : 'Ctrl');

/**
 * Append a hidden input to the form. Recursively unpacks objects and arrays
 * into Laravel-style bracket notation: photos[0][key], photos[0][existing_id],
 * amenities[], etc.
 */
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

function save() {
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
            :href="cancelHref"
            class="px-4 py-2 rounded-md text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
        >
            Cancel
        </a>

        <p class="hidden md:flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500">
            <kbd class="rounded border border-gray-200 dark:border-gray-700 px-1.5 py-0.5 text-[10px] font-medium text-gray-600 dark:text-gray-300 bg-gray-50 dark:bg-gray-800">{{ modKey }}</kbd>
            +
            <kbd class="rounded border border-gray-200 dark:border-gray-700 px-1.5 py-0.5 text-[10px] font-medium text-gray-600 dark:text-gray-300 bg-gray-50 dark:bg-gray-800">Enter</kbd>
            to save
        </p>

        <div class="flex-1"></div>

        <button
            type="button"
            :disabled="submitting"
            @click="save"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium transition cursor-pointer disabled:opacity-90 disabled:cursor-not-allowed"
        >
            <svg v-if="submitting" class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-opacity="0.25"/>
                <path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
            </svg>
            <svg v-else class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                <polyline points="17 21 17 13 7 13 7 21"/>
                <polyline points="7 3 7 8 15 8"/>
            </svg>
            {{ submitting ? 'Saving...' : 'Save changes' }}
        </button>
    </div>
</template>
