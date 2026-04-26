<script setup>
import { ref, computed, inject, toRef } from 'vue';

const form = inject('addListingForm');
const featureOnHomepage = toRef(form, 'is_featured');

const { validateAll } = inject('addListingFormValidate', { validateAll: () => true });
const scrollFormToTop = inject('scrollFormToTop', () => {});

const submitting = ref(false);

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const isMac = computed(() => /Mac|iPod|iPhone|iPad/.test(navigator.platform));
const modKey = computed(() => isMac.value ? '⌘' : 'Ctrl');

/**
 * Append a hidden input to the form. Recursively unpacks objects and arrays
 * into Laravel-style bracket notation: photos[0][key], amenities[], etc.
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

function publish(intent = 'publish') {
    if (submitting.value) return;

    // Run client-side validation first — same rules as the server, but without
    // the round-trip. Server still re-validates as the source of truth.
    if (!validateAll()) {
        scrollFormToTop();
        return;
    }

    submitting.value = true;

    // Build a hidden native form, submit it. Browser handles everything:
    // redirect on success → next page loads with `success` flash + AdminToast.
    // Validation failure → Laravel redirects back, Blade re-renders with $errors
    // + old() (picked up by AdminAddListing.vue mount logic).
    const formEl = document.createElement('form');
    formEl.method = 'POST';
    formEl.action = '/admin/listings/admin-create';
    formEl.style.display = 'none';

    appendHidden(formEl, '_token', csrfToken);
    appendHidden(formEl, 'intent', intent);

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
            href="/admin"
            class="px-4 py-2 rounded-md text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
        >
            Cancel
        </a>

        <p class="hidden md:flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500">
            <kbd class="rounded border border-gray-200 dark:border-gray-700 px-1.5 py-0.5 text-[10px] font-medium text-gray-600 dark:text-gray-300 bg-gray-50 dark:bg-gray-800">{{ modKey }}</kbd>
            +
            <kbd class="rounded border border-gray-200 dark:border-gray-700 px-1.5 py-0.5 text-[10px] font-medium text-gray-600 dark:text-gray-300 bg-gray-50 dark:bg-gray-800">Enter</kbd>
            to publish
        </p>

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
            @click="publish('publish-and-add-another')"
            class="px-4 py-2 rounded-md border border-gray-200 dark:border-gray-700 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
        >
            Publish &amp; add another
        </button>

        <button
            type="button"
            :disabled="submitting"
            @click="publish('publish')"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium transition cursor-pointer disabled:opacity-90 disabled:cursor-not-allowed"
        >
            <svg v-if="submitting" class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-opacity="0.25"/>
                <path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
            </svg>
            <svg v-else class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 10h11M9 10l-4 4 4 4M20 6V4"/>
            </svg>
            {{ submitting ? 'Publishing...' : 'Publish listing' }}
        </button>
    </div>
</template>
