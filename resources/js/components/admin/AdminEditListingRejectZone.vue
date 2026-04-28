<script setup>
import { ref, inject, computed } from 'vue';

const form = inject('addListingForm');

const initial            = window.__INITIAL_EDIT_LISTING__ ?? {};
const initialRejectErrs  = initial.rejectErrors ?? null;

const errors     = ref(initialRejectErrs ?? {});
const submitting = ref(false);

const visible     = computed(() => form?.from === 'unverified');
const listingUuid = initial.listingUuid ?? '';
const csrfToken   = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

function submit() {
    if (submitting.value) return;
    submitting.value = true;

    const formEl = document.createElement('form');
    formEl.method = 'POST';
    formEl.action = `/admin/listings/${listingUuid}/reject`;
    formEl.style.display = 'none';

    const append = (name, value) => {
        const i = document.createElement('input');
        i.type  = 'hidden';
        i.name  = name;
        i.value = value;
        formEl.appendChild(i);
    };
    append('_token',  csrfToken);
    append('_method', 'PUT');

    document.body.appendChild(formEl);
    formEl.submit();
}
</script>

<template>
    <div
        v-if="visible"
        class="mt-8 rounded-lg border border-red-200 dark:border-red-900/60 bg-red-50/40 dark:bg-red-950/20"
    >
        <div class="px-6 py-3 border-b border-red-200 dark:border-red-900/60 flex items-center gap-2">
            <svg class="w-5 h-5 text-red-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            <h2 class="text-base font-semibold text-red-700 dark:text-red-300">Danger Zone</h2>
        </div>

        <div class="px-6 py-5 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="flex-1">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Reject this listing</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Marks this listing as rejected and removes it from the unverified queue.
                </p>

                <div
                    v-if="errors.listing"
                    class="mt-3 rounded-md border border-red-200 dark:border-red-900/60 bg-red-50 dark:bg-red-950/30 px-3 py-2 text-sm text-red-800 dark:text-red-200"
                    role="alert"
                >
                    {{ errors.listing[0] }}
                </div>
            </div>
            <button
                type="button"
                @click="submit"
                :disabled="submitting"
                class="shrink-0 inline-flex items-center gap-1.5 px-4 py-2 rounded-md text-sm font-medium border border-red-300 dark:border-red-800/80 text-red-700 dark:text-red-300 bg-white dark:bg-gray-900 hover:bg-red-50 dark:hover:bg-red-950/40 transition cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
            >
                <svg v-if="submitting" class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-opacity="0.25"/>
                    <path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                </svg>
                <svg v-else class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" />
                    <path d="m15 9-6 6M9 9l6 6" />
                </svg>
                {{ submitting ? 'Rejecting…' : 'Reject' }}
            </button>
        </div>
    </div>
</template>
