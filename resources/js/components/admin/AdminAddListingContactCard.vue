<script setup>
import { inject, ref } from 'vue';
import axios from '../../axios';

const form = inject('addListingForm');
const errors = inject('addListingFormErrors', ref({}));
const { validateField, clearFieldError } = inject('addListingFormValidate', {
    validateField: () => true,
    clearFieldError: () => {},
});

const errorFor = (field) => errors.value?.[field]?.[0] ?? null;
const inputErrorClass = 'border-red-400 dark:border-red-500 focus:border-red-500 focus:ring-red-500';

const lookupStatus = ref('idle');
const matchedName  = ref(null);

async function onPhoneBlur() {
    validateField('contact.phone');

    const phone = (form.contact.phone ?? '').trim();
    if (!phone) {
        form.contact.uuid = '';
        lookupStatus.value = 'idle';
        matchedName.value  = null;
        return;
    }

    lookupStatus.value = 'searching';
    const res = await axios.get('/admin/api/listing-contacts/find-by-phone', { params: { phone } });
    const contact = res.data?.contact;

    if (contact) {
        form.contact.uuid  = contact.uuid;
        form.contact.name  = contact.name  ?? '';
        form.contact.notes = contact.notes ?? '';
        matchedName.value  = contact.name;
        lookupStatus.value = 'found';
    } else {
        form.contact.uuid  = '';
        matchedName.value  = null;
        lookupStatus.value = 'not-found';
    }
}
</script>

<template>
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm p-6 space-y-5">
        <div>
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Contact</h3>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Owner or broker contact for this listing. Phone match auto-fills name and notes from existing contacts.
            </p>
        </div>

        <div>
            <label for="contact-phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Phone <span class="text-red-500">*</span>
            </label>
            <input
                id="contact-phone"
                v-model="form.contact.phone"
                type="tel"
                placeholder="09171234567"
                @focus="clearFieldError('contact.phone')"
                @blur="onPhoneBlur"
                :class="[
                    'mt-1 w-full rounded-md border bg-white dark:bg-gray-800 px-3.5 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:ring-1 outline-none transition',
                    errorFor('contact.phone')
                        ? inputErrorClass
                        : 'border-gray-200 dark:border-gray-700 focus:border-orange-500 focus:ring-orange-500',
                ]"
            >
            <p v-if="errorFor('contact.phone')" class="mt-1 text-xs text-red-600 dark:text-red-400">
                {{ errorFor('contact.phone') }}
            </p>
            <p v-else-if="lookupStatus === 'found'" class="mt-1 text-xs text-green-600 dark:text-green-400">
                ✓ {{ matchedName || 'Existing contact' }} · loaded
            </p>
            <p v-else-if="lookupStatus === 'not-found'" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                No existing contact — a new one will be created on publish.
            </p>
            <p v-else-if="lookupStatus === 'searching'" class="mt-1 text-xs text-gray-400 dark:text-gray-500">
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
                @focus="clearFieldError('contact.name')"
                @blur="validateField('contact.name')"
                :class="[
                    'mt-1 w-full rounded-md border bg-white dark:bg-gray-800 px-3.5 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:ring-1 outline-none transition',
                    errorFor('contact.name')
                        ? inputErrorClass
                        : 'border-gray-200 dark:border-gray-700 focus:border-orange-500 focus:ring-orange-500',
                ]"
            >
            <p v-if="errorFor('contact.name')" class="mt-1 text-xs text-red-600 dark:text-red-400">
                {{ errorFor('contact.name') }}
            </p>
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
                @focus="clearFieldError('contact.notes')"
                @blur="validateField('contact.notes')"
                :class="[
                    'mt-1 w-full rounded-md border bg-white dark:bg-gray-800 px-3.5 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:ring-1 outline-none transition resize-y',
                    errorFor('contact.notes')
                        ? inputErrorClass
                        : 'border-gray-200 dark:border-gray-700 focus:border-orange-500 focus:ring-orange-500',
                ]"
            ></textarea>
            <p v-if="errorFor('contact.notes')" class="mt-1 text-xs text-red-600 dark:text-red-400">
                {{ errorFor('contact.notes') }}
            </p>
        </div>
    </div>
</template>
