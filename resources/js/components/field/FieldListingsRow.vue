<script setup>
import { computed } from 'vue';

const props = defineProps({
    row: { type: Object, required: true },
    isSearching: { type: Boolean, default: false },
});

const dateTimeFormatter = new Intl.DateTimeFormat('en-US', {
    year: 'numeric', month: 'short', day: '2-digit',
    hour: '2-digit', minute: '2-digit',
});

const formattedAssignedAt = computed(() => {
    if (!props.row.assigned_at) return '';
    return dateTimeFormatter.format(new Date(props.row.assigned_at));
});
</script>

<template>
    <tr class="text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/40">
        <td class="px-4 py-3 font-semibold">{{ row.title }}</td>

        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ row.type_label }}</td>

        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ row.barangay_label }}</td>

        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ row.directions }}</td>

        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ formattedAssignedAt }}</td>

        <td class="px-4 py-3">
            <div class="flex items-center gap-1 justify-center">
                <a
                    :href="`/field/listings/${row.uuid}/request-verification`"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium text-emerald-700 dark:text-emerald-300 hover:bg-emerald-50 dark:hover:bg-emerald-950/30 cursor-pointer"
                    title="Request Verification"
                >
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                        <polyline points="22 4 12 14.01 9 11.01" />
                    </svg>
                    Verify
                </a>
                <a
                    :href="`/field/listings/${row.uuid}/edit`"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer"
                    title="Edit"
                >
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 20h9" />
                        <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" />
                    </svg>
                    Edit
                </a>
            </div>
        </td>
    </tr>
</template>
