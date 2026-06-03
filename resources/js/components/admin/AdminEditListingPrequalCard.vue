<script setup>
import { computed, inject, ref } from 'vue';

const props = defineProps({
    contactPhone:           { type: String, default: null },
    persistedPrequalStatus: { type: String, default: null },
});

const form = inject('addListingForm');
const errors = inject('addListingFormErrors', ref({}));
const prequalError = computed(() => errors.value?.prequal_status?.[0] ?? null);

// Prequal lock-in matrix (mirrors the server-side guard):
//   persisted=called_yes → only called_yes pill (locked — successful call
//                          can't be rewritten as not_called or no_answer)
//   persisted=no_answer  → called_yes + no_answer (rollback blocked)
//   else → all three pills (including when current form state is no_answer
//          but persisted isn't yet — admin can still un-click before saving)
const visibleOptions = computed(() => {
    if (props.persistedPrequalStatus === 'called_yes') {
        return options.filter(o => o.value === 'called_yes');
    }
    if (props.persistedPrequalStatus === 'no_answer') {
        return options.filter(o => o.value !== 'not_called');
    }
    return options;
});

// Treat empty/null as 'not_called' for highlighting purposes — legacy rows
// pre-FRD-023 land with null prequal_status; admin shouldn't have to click
// "Not called" first just to see the default state.
const currentStatus = computed(() => form.prequal_status || 'not_called');

const options = [
    {
        value: 'not_called',
        label: 'Not called',
        activeClass: 'border-gray-300 bg-gray-100 text-gray-800 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200',
    },
    {
        value: 'called_yes',
        label: 'Called: Yes',
        activeClass: 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200',
    },
    {
        value: 'no_answer',
        label: 'No answer',
        activeClass: 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-700 dark:bg-amber-900/40 dark:text-amber-200',
    },
];

const description = computed(() => {
    switch (currentStatus.value) {
        case 'called_yes': return "Owner reachable. Capture call context below, then assign a field officer.";
        case 'no_answer':  return "Owner didn't pick up. Try again later or send a follow-up SMS.";
        default:           return "Reach out to the contact, then mark the result.";
    }
});

// Format E.164 +639XXXXXXXXX as 0917 123 4567 for display.
const formattedContactPhone = computed(() => {
    const phone = form.contact_phone || null;
    if (!phone) return null;
    const digits = phone.replace(/\D/g, '');
    if (digits.length === 12 && digits.startsWith('63')) {
        return '0' + digits.slice(2, 5) + ' ' + digits.slice(5, 8) + ' ' + digits.slice(8);
    }
    return phone;
});
</script>

<template>
    <section class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm p-6 space-y-4">
        <div>
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Pre-qualification</h2>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ description }}
            </p>
        </div>

        <div v-if="formattedContactPhone" class="flex items-center gap-2 text-sm">
            <svg class="w-4 h-4 text-gray-400 dark:text-gray-500 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
            </svg>
            <span class="text-gray-700 dark:text-gray-200 font-mono tabular-nums">{{ formattedContactPhone }}</span>
        </div>

        <div class="flex flex-wrap gap-2 pt-1">
            <button
                v-for="opt in visibleOptions"
                :key="opt.value"
                type="button"
                @click="form.prequal_status = opt.value"
                :class="[
                    'px-3.5 py-1.5 rounded-md text-xs font-medium border transition cursor-pointer',
                    currentStatus === opt.value
                        ? opt.activeClass
                        : 'border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800/50',
                ]"
            >
                {{ opt.label }}
            </button>
        </div>
        <p v-if="prequalError" class="text-xs text-red-600 dark:text-red-400">
            {{ prequalError }}
        </p>
    </section>
</template>
