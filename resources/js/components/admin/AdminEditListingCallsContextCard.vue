<script setup>
import { computed, inject, ref } from 'vue';

const props = defineProps({
    contactTypes:              { type: Array, default: () => [] },
    readOnly:                  { type: Boolean, default: false },
    verificationNotesEditable: { type: Boolean, default: false },
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

// verification_notes can be amended on the verified-edit slice (calls-team
// surface) even when the rest of the call-context fields are readonly.
const verificationNotesReadOnly = computed(() => props.readOnly && !props.verificationNotesEditable);

function fieldClass(field) {
    const fieldReadOnly = field === 'verification_notes' ? verificationNotesReadOnly.value : props.readOnly;
    if (fieldReadOnly) {
        return 'mt-1 w-full rounded-md border bg-gray-50 dark:bg-gray-800/50 border-gray-200 dark:border-gray-700 px-3.5 py-2.5 text-sm text-gray-900 dark:text-white cursor-not-allowed outline-none transition';
    }
    return [
        'mt-1 w-full rounded-md border bg-white dark:bg-gray-800 px-3.5 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:ring-1 outline-none transition resize-y',
        errorFor(field)
            ? inputErrorClass
            : 'border-gray-200 dark:border-gray-700 focus:border-orange-500 focus:ring-orange-500',
    ];
}
</script>

<template>
    <section class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm p-6 space-y-5">
        <div>
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Call context</h2>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                What the calls team captured pre-visit, plus directions the field officer used.
                <span v-if="readOnly && !verificationNotesEditable">Read-only — captured upstream.</span>
                <span v-else-if="readOnly && verificationNotesEditable">Mostly read-only — verification notes can be amended after qualifying calls.</span>
                <span v-else>Edit only if there's a typo.</span>
            </p>
        </div>

        <div>
            <label for="calls-contact-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Contact type
            </label>
            <select
                id="calls-contact-type"
                v-model="form.contact_type"
                :disabled="readOnly"
                @focus="clearFieldError('contact_type')"
                @change="validateField('contact_type')"
                @blur="validateField('contact_type')"
                :class="[
                    readOnly
                        ? 'mt-1 w-full sm:w-1/2 rounded-md border bg-gray-50 dark:bg-gray-800/50 border-gray-200 dark:border-gray-700 px-3.5 py-2.5 text-sm text-gray-900 dark:text-white cursor-not-allowed outline-none transition'
                        : [
                            'mt-1 w-full sm:w-1/2 rounded-md border bg-white dark:bg-gray-800 px-3.5 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-1 outline-none transition',
                            errorFor('contact_type')
                                ? inputErrorClass
                                : 'border-gray-200 dark:border-gray-700 focus:border-orange-500 focus:ring-orange-500',
                        ],
                ]"
            >
                <option value="">— Select —</option>
                <option v-for="ct in contactTypes" :key="ct.value" :value="ct.value">{{ ct.label }}</option>
            </select>
            <p v-if="!readOnly && errorFor('contact_type')" class="mt-1 text-xs text-red-600 dark:text-red-400">
                {{ errorFor('contact_type') }}
            </p>
        </div>

        <div>
            <div class="flex items-baseline justify-between">
                <label for="calls-directions" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Directions to property <span v-if="!readOnly" class="text-red-500">*</span>
                </label>
                <span :class="[
                    'text-xs',
                    directionsCharCount > 500 ? 'text-red-600 dark:text-red-400' : 'text-gray-400 dark:text-gray-500'
                ]">
                    {{ directionsCharCount }}/500
                </span>
            </div>
            <textarea
                id="calls-directions"
                v-model="form.directions"
                rows="3"
                placeholder="Landmark cues, gate code, who to ask for at reception, etc."
                :readonly="readOnly"
                @focus="clearFieldError('directions')"
                @blur="validateField('directions')"
                :class="fieldClass('directions')"
            ></textarea>
            <p v-if="!readOnly && errorFor('directions')" class="mt-1 text-xs text-red-600 dark:text-red-400">
                {{ errorFor('directions') }}
            </p>
        </div>

        <div>
            <div class="flex items-baseline justify-between">
                <label for="calls-verification-notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
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
                id="calls-verification-notes"
                v-model="form.verification_notes"
                rows="4"
                placeholder="What did the owner say? Any flags noted during the visit?"
                :readonly="verificationNotesReadOnly"
                @focus="clearFieldError('verification_notes')"
                @blur="validateField('verification_notes')"
                :class="fieldClass('verification_notes')"
            ></textarea>
            <p v-if="!verificationNotesReadOnly && errorFor('verification_notes')" class="mt-1 text-xs text-red-600 dark:text-red-400">
                {{ errorFor('verification_notes') }}
            </p>
        </div>
    </section>
</template>
