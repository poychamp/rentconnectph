<script setup>
import { computed } from 'vue';

const props = defineProps({
    row: { type: Object, required: true },
});

const dateTimeFormatter = new Intl.DateTimeFormat('en-US', {
    year: 'numeric', month: 'short', day: '2-digit',
    hour: '2-digit', minute: '2-digit',
});

const formattedSubmittedAt = computed(() => {
    if (!props.row.visited_at) return '';
    return dateTimeFormatter.format(new Date(props.row.visited_at));
});
</script>

<template>
    <tr class="text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/40">
        <td class="px-4 py-3 font-semibold">{{ row.title }}</td>
        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ row.type_label }}</td>
        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ row.barangay_label }}</td>
        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ row.directions }}</td>
        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ formattedSubmittedAt }}</td>

        <td class="px-4 py-3">
            <div class="flex items-center justify-center">
                <a
                    :href="`/field/listings/${row.uuid}/preview`"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer"
                    title="Preview"
                >
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                        <circle cx="12" cy="12" r="3" />
                    </svg>
                    Preview
                </a>
            </div>
        </td>
    </tr>
</template>
