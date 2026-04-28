<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    listingUuid: { type: String, required: true },
    errors:      { type: Object, default: null },
});

const submitting = ref(false);
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const formAction = computed(() => `/admin/listings/${props.listingUuid}/restore`);

function submit() {
    if (submitting.value) return;
    submitting.value = true;

    const formEl = document.createElement('form');
    formEl.method = 'POST';
    formEl.action = formAction.value;
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
    <div class="shrink-0 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800 px-6 py-3 flex items-center gap-4">
        <a
            href="/admin/deactivated-listings"
            class="px-4 py-2 rounded-md text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
        >
            Cancel
        </a>

        <div class="flex-1"></div>

        <p
            v-if="errors && errors.listing"
            class="text-sm text-red-600 dark:text-red-400"
            role="alert"
        >{{ errors.listing[0] }}</p>

        <button
            type="button"
            :disabled="submitting"
            @click="submit"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium transition cursor-pointer disabled:opacity-90 disabled:cursor-not-allowed"
        >
            <svg v-if="submitting" class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-opacity="0.25"/>
                <path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
            </svg>
            <svg v-else class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 12a9 9 0 1 0 3-6.7L3 8" />
                <path d="M3 3v5h5" />
            </svg>
            {{ submitting ? 'Restoring…' : 'Restore' }}
        </button>
    </div>
</template>
