<script setup>
import { computed } from 'vue';

const props = defineProps({
    prereqs: { type: Object, required: true },
});

const rules = computed(() => [
    {
        key:    'has_title',
        label:  'Title set',
        ok:     Boolean(props.prereqs?.has_title),
        fix:    'Add a title before submitting.',
    },
    {
        key:    'has_directions',
        label:  'Directions set',
        ok:     Boolean(props.prereqs?.has_directions),
        fix:    'Add directions before submitting.',
    },
    {
        key:    'has_lat_lng',
        label:  'Map pin set',
        ok:     Boolean(props.prereqs?.has_lat_lng),
        fix:    'Drop a map pin before submitting.',
    },
    {
        key:    'has_at_least_one_new_photo',
        label:  'At least one new photo uploaded',
        ok:     Boolean(props.prereqs?.has_at_least_one_new_photo),
        fix:    'Upload a new photo from your visit before submitting.',
    },
]);

const allOk = computed(() => rules.value.every(r => r.ok));
</script>

<template>
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm p-6">
        <div class="flex items-baseline justify-between">
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Ready to submit?</h2>
            <span
                class="text-xs font-medium"
                :class="allOk
                    ? 'text-emerald-600 dark:text-emerald-400'
                    : 'text-red-600 dark:text-red-400'"
            >
                {{ allOk ? 'All prerequisites met' : 'Some items still needed' }}
            </span>
        </div>

        <ul class="mt-3 space-y-2">
            <li
                v-for="rule in rules"
                :key="rule.key"
                class="flex items-start gap-3"
            >
                <svg
                    v-if="rule.ok"
                    class="w-5 h-5 shrink-0 text-emerald-500 dark:text-emerald-400 mt-0.5"
                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                >
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                    <polyline points="22 4 12 14.01 9 11.01" />
                </svg>
                <svg
                    v-else
                    class="w-5 h-5 shrink-0 text-red-500 dark:text-red-400 mt-0.5"
                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                >
                    <circle cx="12" cy="12" r="10" />
                    <line x1="15" y1="9" x2="9" y2="15" />
                    <line x1="9" y1="9" x2="15" y2="15" />
                </svg>

                <div class="flex-1 min-w-0">
                    <p
                        class="text-sm"
                        :class="rule.ok
                            ? 'text-gray-700 dark:text-gray-300'
                            : 'text-gray-900 dark:text-gray-100 font-medium'"
                    >
                        {{ rule.label }}
                    </p>
                    <p
                        v-if="!rule.ok"
                        class="mt-0.5 text-xs text-gray-500 dark:text-gray-400"
                    >
                        {{ rule.fix }}
                    </p>
                </div>
            </li>
        </ul>
    </div>
</template>
