<script setup>
import { computed } from 'vue';

const props = defineProps({
    row: { type: Object, required: true },
});

const formattedPrice = computed(() =>
    new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 }).format(props.row.price)
);

const formattedDate = computed(() =>
    new Intl.DateTimeFormat('en-US', { year: 'numeric', month: 'short', day: '2-digit' }).format(new Date(props.row.verified_at))
);
</script>

<template>
    <tr class="text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/40">
        <td class="px-4 py-3 font-semibold">{{ row.name }}</td>

        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ row.type_label }}</td>

        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ row.barangay_label }}</td>

        <td class="px-4 py-3 font-medium">{{ formattedPrice }}</td>

        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ formattedDate }}</td>

        <td class="px-4 py-3">
            <div class="flex items-center gap-1 justify-center">
                <a :href="`/listings/${row.uuid}`" target="_blank" rel="noopener"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer"
                    title="View on website">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z" />
                        <circle cx="12" cy="12" r="3" />
                    </svg>
                    View
                </a>

                <button type="button"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer"
                    title="Edit">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 20h9" />
                        <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" />
                    </svg>
                    Edit
                </button>

                <button type="button"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30 cursor-pointer"
                    title="Deactivate">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6" />
                        <path d="M19 6 17.5 20a2 2 0 0 1-2 2h-7a2 2 0 0 1-2-2L5 6M10 11v6M14 11v6M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2" />
                    </svg>
                    Deactivate
                </button>
            </div>
        </td>
    </tr>
</template>
