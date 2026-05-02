<script setup>
import { inject } from 'vue';

defineProps({
    sourceSites: { type: Array, default: () => [] },
});

const form = inject('addListingForm');

const inputClass = 'mt-1 w-full rounded-md border px-3.5 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 outline-none transition bg-gray-50 dark:bg-gray-800/50 border-gray-200 dark:border-gray-700 cursor-not-allowed';

const selectClass = 'mt-1 w-full rounded-md border px-3.5 py-2.5 text-sm text-gray-900 dark:text-white outline-none transition bg-gray-50 dark:bg-gray-800/50 border-gray-200 dark:border-gray-700 cursor-not-allowed';
</script>

<template>
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm p-6 space-y-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <label for="contact-phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Contact phone
                </label>
                <input
                    id="contact-phone"
                    type="tel"
                    placeholder="09171234567"
                    :value="form.contact_phone"
                    readonly
                    :class="inputClass"
                >
            </div>

            <div>
                <label for="source-site" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Source site
                </label>
                <select
                    id="source-site"
                    :value="form.source_site"
                    disabled
                    :class="selectClass"
                >
                    <option value="">— None —</option>
                    <option v-for="ss in sourceSites" :key="ss.value" :value="ss.value">{{ ss.label }}</option>
                </select>
            </div>

            <div>
                <label for="source-url" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Source URL
                </label>
                <input
                    id="source-url"
                    type="url"
                    placeholder="https://www.olx.ph/..."
                    :value="form.source_url"
                    readonly
                    :class="inputClass"
                >
            </div>
        </div>
    </div>
</template>
