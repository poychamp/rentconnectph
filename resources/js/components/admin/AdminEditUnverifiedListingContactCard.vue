<script setup>
import { computed, inject, ref } from 'vue';
import axios from '../../axios';

const form = inject('addListingForm');
const errors = inject('addListingFormErrors', ref({}));
const { validateField, clearFieldError } = inject('addListingFormValidate', {
    validateField: () => true,
    clearFieldError: () => {},
});
// Page-provided gate so the publish bar can disable Save while the lookup
// is in flight — submitting now would ship a stale uuid/name/notes that
// the lookup is about to overwrite.
const lookupPending = inject('contactPhoneLookupPending', ref(false));
// Server-persisted prequal state — locks the contact card to read-only
// when the listing has already been called (mirror of the server-side
// rule: persisted=called_yes silently ignores the contact section).
// Read the PERSISTED value, not the live form state — admin can flip the
// prequal pill in-page without freezing inputs mid-edit.
const persistedPrequalStatus = inject('persistedPrequalStatus', null);
const isLocked = computed(() => persistedPrequalStatus === 'called_yes');

const errorFor = (field) => errors.value?.[field]?.[0] ?? null;
const inputErrorClass = 'border-red-400 dark:border-red-500 focus:border-red-500 focus:ring-red-500';
const lockedInputClass = 'bg-gray-50 dark:bg-gray-800/50 border-gray-200 dark:border-gray-700 cursor-not-allowed';

const lookupStatus = ref('idle');
const matchedName  = ref(null);

async function onPhoneBlur() {
    if (isLocked.value) return;
    validateField('contact.phone');

    const phone = (form.contact.phone ?? '').trim();
    if (!phone) {
        form.contact.uuid = '';
        lookupStatus.value = 'idle';
        matchedName.value  = null;
        lookupPending.value = false;
        return;
    }

    lookupStatus.value = 'searching';
    lookupPending.value = true;
    try {
        const res = await axios.get('/admin/api/listing-contacts/find-by-phone', { params: { phone } });
        const contact = res.data?.contact;

        if (contact) {
            form.contact.uuid          = contact.uuid;
            form.contact.name          = contact.name          ?? '';
            form.contact.notes         = contact.notes         ?? '';
            form.contact.is_show_name  = contact.is_show_name  ?? false;
            form.contact.is_show_notes = contact.is_show_notes ?? false;
            matchedName.value  = contact.name;
            lookupStatus.value = 'found';
        } else {
            form.contact.uuid  = '';
            matchedName.value  = null;
            lookupStatus.value = 'not-found';
        }
    } finally {
        lookupPending.value = false;
    }
}
</script>

<template>
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm p-6 space-y-5">
        <div>
            <div class="flex items-center gap-2">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Contact</h3>
                <span v-if="isLocked" class="inline-flex items-center gap-1 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 text-[10px] font-medium px-2 py-0.5">
                    <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    Locked
                </span>
            </div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                <template v-if="isLocked">Contact is frozen — this listing has already been called.</template>
                <template v-else>Owner or broker contact for this listing. Phone match auto-fills name and notes from existing contacts.</template>
            </p>
        </div>

        <div>
            <label for="contact-phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Phone <span v-if="!isLocked" class="text-red-500">*</span>
            </label>
            <input
                id="contact-phone"
                v-model="form.contact.phone"
                type="tel"
                placeholder="09171234567"
                :readonly="isLocked"
                @focus="clearFieldError('contact.phone')"
                @blur="onPhoneBlur"
                :class="[
                    'mt-1 w-full rounded-md border px-3.5 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 outline-none transition',
                    isLocked
                        ? lockedInputClass
                        : (errorFor('contact.phone')
                            ? `bg-white dark:bg-gray-800 ${inputErrorClass} focus:ring-1`
                            : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 focus:border-orange-500 focus:ring-1 focus:ring-orange-500'),
                ]"
            >
            <p v-if="!isLocked && errorFor('contact.phone')" class="mt-1 text-xs text-red-600 dark:text-red-400">
                {{ errorFor('contact.phone') }}
            </p>
            <p v-else-if="!isLocked && lookupStatus === 'found'" class="mt-1 text-xs text-green-600 dark:text-green-400">
                ✓ {{ matchedName || 'Existing contact' }} · loaded
            </p>
            <p v-else-if="!isLocked && lookupStatus === 'not-found'" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                No existing contact — a new one will be created on save.
            </p>
            <p v-else-if="!isLocked && lookupStatus === 'searching'" class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                Looking up…
            </p>
        </div>

        <div>
            <label for="contact-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Name
            </label>
            <input
                id="contact-name"
                v-model="form.contact.name"
                type="text"
                placeholder="e.g. Maria Reyes"
                :readonly="isLocked"
                @focus="clearFieldError('contact.name')"
                @blur="validateField('contact.name')"
                :class="[
                    'mt-1 w-full rounded-md border px-3.5 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 outline-none transition',
                    isLocked
                        ? lockedInputClass
                        : (errorFor('contact.name')
                            ? `bg-white dark:bg-gray-800 ${inputErrorClass} focus:ring-1`
                            : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 focus:border-orange-500 focus:ring-1 focus:ring-orange-500'),
                ]"
            >
            <p v-if="!isLocked && errorFor('contact.name')" class="mt-1 text-xs text-red-600 dark:text-red-400">
                {{ errorFor('contact.name') }}
            </p>
            <label class="mt-2 inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300" :class="isLocked ? 'cursor-not-allowed' : 'cursor-pointer'">
                <input
                    type="checkbox"
                    v-model="form.contact.is_show_name"
                    :disabled="isLocked"
                    class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-orange-500 focus:ring-orange-500 disabled:opacity-60 disabled:cursor-not-allowed"
                >
                Show name to renters
            </label>
        </div>

        <div>
            <label for="contact-notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Notes
            </label>
            <textarea
                id="contact-notes"
                v-model="form.contact.notes"
                rows="3"
                placeholder="Standing context: who they are, response style, units they manage, etc."
                :readonly="isLocked"
                @focus="clearFieldError('contact.notes')"
                @blur="validateField('contact.notes')"
                :class="[
                    'mt-1 w-full rounded-md border px-3.5 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 outline-none transition resize-y',
                    isLocked
                        ? lockedInputClass
                        : (errorFor('contact.notes')
                            ? `bg-white dark:bg-gray-800 ${inputErrorClass} focus:ring-1`
                            : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 focus:border-orange-500 focus:ring-1 focus:ring-orange-500'),
                ]"
            ></textarea>
            <p v-if="!isLocked && errorFor('contact.notes')" class="mt-1 text-xs text-red-600 dark:text-red-400">
                {{ errorFor('contact.notes') }}
            </p>
            <label class="mt-2 inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300" :class="isLocked ? 'cursor-not-allowed' : 'cursor-pointer'">
                <input
                    type="checkbox"
                    v-model="form.contact.is_show_notes"
                    :disabled="isLocked"
                    class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-orange-500 focus:ring-orange-500 disabled:opacity-60 disabled:cursor-not-allowed"
                >
                Show notes to renters
            </label>
        </div>
    </div>
</template>
