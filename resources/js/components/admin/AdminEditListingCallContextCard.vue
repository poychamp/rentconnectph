<script setup>
import { computed, inject, ref } from 'vue';

defineProps({
    contactTypes: { type: Array, default: () => [] },
    fieldUsers:   { type: Array, default: () => [] },
});

const form = inject('addListingForm');
const errors = inject('addListingFormErrors', ref({}));
const { validateField, clearFieldError } = inject('addListingFormValidate', {
    validateField: () => true,
    clearFieldError: () => {},
});

const errorFor = (field) => errors.value?.[field]?.[0] ?? null;

const inputErrorClass = 'border-red-400 dark:border-red-500 focus:border-red-500 focus:ring-red-500';

const directionsCharCount = computed(() => (form.directions ?? '').length);
const verificationNotesCharCount = computed(() => (form.verification_notes ?? '').length);

// directions + contact_type are required when prequal_status === 'called_yes'.
// Asterisk reflects that — assigned_to and verification_notes stay optional.
const isCalledYes = computed(() => form.prequal_status === 'called_yes');
</script>

<template>
    <section class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm p-6 space-y-5">
        <div>
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Call context</h2>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Capture what the owner said so the field officer has context before the visit.
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label for="contact-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Contact type <span v-if="isCalledYes" class="text-red-500">*</span>
                </label>
                <select
                    id="contact-type"
                    v-model="form.contact_type"
                    @focus="clearFieldError('contact_type')"
                    @change="validateField('contact_type')"
                    @blur="validateField('contact_type')"
                    :class="[
                        'mt-1 w-full rounded-md border bg-white dark:bg-gray-800 px-3.5 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-1 outline-none transition',
                        errorFor('contact_type')
                            ? inputErrorClass
                            : 'border-gray-200 dark:border-gray-700 focus:border-orange-500 focus:ring-orange-500',
                    ]"
                >
                    <option value="">— Select —</option>
                    <option v-for="ct in contactTypes" :key="ct.value" :value="ct.value">{{ ct.label }}</option>
                </select>
                <p v-if="errorFor('contact_type')" class="mt-1 text-xs text-red-600 dark:text-red-400">
                    {{ errorFor('contact_type') }}
                </p>
            </div>

            <div>
                <label for="assigned-to" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Assign field officer
                </label>
                <select
                    id="assigned-to"
                    v-model="form.assigned_to"
                    @focus="clearFieldError('assigned_to')"
                    @change="validateField('assigned_to')"
                    @blur="validateField('assigned_to')"
                    :class="[
                        'mt-1 w-full rounded-md border bg-white dark:bg-gray-800 px-3.5 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-1 outline-none transition',
                        errorFor('assigned_to')
                            ? inputErrorClass
                            : 'border-gray-200 dark:border-gray-700 focus:border-orange-500 focus:ring-orange-500',
                    ]"
                >
                    <option :value="null">— Unassigned —</option>
                    <option v-for="u in fieldUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
                <p v-if="errorFor('assigned_to')" class="mt-1 text-xs text-red-600 dark:text-red-400">
                    {{ errorFor('assigned_to') }}
                </p>
            </div>
        </div>

        <div>
            <div class="flex items-baseline justify-between">
                <label for="directions" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Directions to property <span v-if="isCalledYes" class="text-red-500">*</span>
                </label>
                <span :class="[
                    'text-xs',
                    directionsCharCount > 500 ? 'text-red-600 dark:text-red-400' : 'text-gray-400 dark:text-gray-500'
                ]">
                    {{ directionsCharCount }}/500
                </span>
            </div>
            <textarea
                id="directions"
                v-model="form.directions"
                rows="3"
                placeholder="Landmark cues, gate code, who to ask for at reception, etc."
                @focus="clearFieldError('directions')"
                @blur="validateField('directions')"
                :class="[
                    'mt-1 w-full rounded-md border bg-white dark:bg-gray-800 px-3.5 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:ring-1 outline-none transition resize-y',
                    errorFor('directions')
                        ? inputErrorClass
                        : 'border-gray-200 dark:border-gray-700 focus:border-orange-500 focus:ring-orange-500',
                ]"
            ></textarea>
            <p v-if="errorFor('directions')" class="mt-1 text-xs text-red-600 dark:text-red-400">
                {{ errorFor('directions') }}
            </p>
        </div>

        <div>
            <div class="flex items-baseline justify-between">
                <label for="verification-notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Verification Notes
                </label>
                <span :class="[
                    'text-xs',
                    verificationNotesCharCount > 2000 ? 'text-red-600 dark:text-red-400' : 'text-gray-400 dark:text-gray-500'
                ]">
                    {{ verificationNotesCharCount }}/2000
                </span>
            </div>
            <textarea
                id="verification-notes"
                v-model="form.verification_notes"
                rows="4"
                placeholder="What did the owner say? Any flags to watch for during the visit?"
                @focus="clearFieldError('verification_notes')"
                @blur="validateField('verification_notes')"
                :class="[
                    'mt-1 w-full rounded-md border bg-white dark:bg-gray-800 px-3.5 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:ring-1 outline-none transition resize-y',
                    errorFor('verification_notes')
                        ? inputErrorClass
                        : 'border-gray-200 dark:border-gray-700 focus:border-orange-500 focus:ring-orange-500',
                ]"
            ></textarea>
            <p v-if="errorFor('verification_notes')" class="mt-1 text-xs text-red-600 dark:text-red-400">
                {{ errorFor('verification_notes') }}
            </p>
        </div>
    </section>
</template>
