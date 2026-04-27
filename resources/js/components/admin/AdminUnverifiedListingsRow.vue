<script setup>
import { computed } from 'vue';

const props = defineProps({
    row: { type: Object, required: true },
});

const formattedPrice = computed(() =>
    new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 }).format(props.row.price)
);

const dateTimeFormatter = new Intl.DateTimeFormat('en-US', {
    year: 'numeric', month: 'short', day: '2-digit',
    hour: '2-digit', minute: '2-digit',
});

const formattedDate = computed(() =>
    dateTimeFormatter.format(new Date(props.row.created_at))
);

const formattedUpdatedAt = computed(() =>
    dateTimeFormatter.format(new Date(props.row.updated_at))
);
</script>

<template>
    <tr class="text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/40">
        <td class="px-4 py-3 font-semibold">{{ row.name }}</td>

        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ row.type_label }}</td>

        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ row.barangay_label }}</td>

        <td class="px-4 py-3 font-medium">{{ formattedPrice }}</td>

        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ formattedDate }}</td>

        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ formattedUpdatedAt }}</td>

        <td class="px-4 py-3">
            <div class="flex items-center gap-1 justify-center">
                <a :href="`/admin/listings/${row.uuid}/edit?from=unverified`"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer"
                    title="Edit">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 20h9" />
                        <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" />
                    </svg>
                    Edit
                </a>

                <button type="button"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30 cursor-pointer"
                    title="Reject">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10" />
                        <path d="m15 9-6 6M9 9l6 6" />
                    </svg>
                    Reject
                </button>
            </div>
        </td>
    </tr>
</template>
