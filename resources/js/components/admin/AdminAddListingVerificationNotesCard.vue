<script setup>
import { computed, inject, ref } from 'vue';

const form = inject('addListingForm');
const errors = inject('addListingFormErrors', ref({}));
const { validateField, clearFieldError } = inject('addListingFormValidate', {
    validateField: () => true,
    clearFieldError: () => {},
});

const errorFor = (field) => errors.value?.[field]?.[0] ?? null;

const inputErrorClass = 'border-red-400 dark:border-red-500 focus:border-red-500 focus:ring-red-500';

const verificationNotesCharCount = computed(() => (form.verification_notes ?? '').length);
</script>

<template>
    <section class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm p-6 space-y-5">
        <div>
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Verification notes</h2>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Anything the field officer should know before visiting? Optional.
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
