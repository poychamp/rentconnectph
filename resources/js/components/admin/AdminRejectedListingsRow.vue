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

const formattedCreatedAt = computed(() =>
    dateTimeFormatter.format(new Date(props.row.created_at))
);

const formattedRejectedAt = computed(() =>
    dateTimeFormatter.format(new Date(props.row.rejected_at))
);
</script>

<template>
    <tr class="text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/40">
        <td class="px-4 py-3 font-semibold">{{ row.name }}</td>

        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ row.type_label }}</td>

        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ row.barangay_label }}</td>

        <td class="px-4 py-3 font-medium">{{ formattedPrice }}</td>

        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ formattedCreatedAt }}</td>

        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ formattedRejectedAt }}</td>

        <td class="px-4 py-3">
            <div class="flex items-center gap-1 justify-center">
                <a :href="`/admin/listings/${row.uuid}/reopen`"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/30 cursor-pointer"
                    title="Reopen">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 12a9 9 0 1 0 3-6.7L3 8" />
                        <path d="M3 3v5h5" />
                    </svg>
                    Reopen
                </a>
            </div>
        </td>
    </tr>
</template>
