<script setup>
import { ref, computed, inject, toRef } from 'vue';

const form = inject('addListingForm');
const featureOnHomepage = toRef(form, 'feature_on_homepage');

const submitting = ref(false);

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const isMac = computed(() => /Mac|iPod|iPhone|iPad/.test(navigator.platform));
const modKey = computed(() => isMac.value ? '⌘' : 'Ctrl');

async function publish(intent = 'publish') {
    if (submitting.value) return;
    submitting.value = true;

    const payload = { ...form, intent };

    const res = await fetch('/admin/listings/admin-create', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN':  csrfToken,
            'Accept':        'text/html',
        },
        body: JSON.stringify(payload),
    });

    // Phase 1 wiring check — backend dd's the payload. Replace the page with the dump
    // so we can inspect it. Phase 2 will redirect on success / re-render on validation error.
    const html = await res.text();
    document.open();
    document.write(html);
    document.close();
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
