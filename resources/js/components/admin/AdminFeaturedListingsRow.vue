<script setup>
import { computed } from 'vue';

const props = defineProps({
    row: { type: Object, required: true },
});

const formattedPrice = computed(() =>
    new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 }).format(props.row.price)
);
</script>

<template>
    <tr class="text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/40">
        <td class="px-3 py-3 w-10">
            <div
                class="drag-handle inline-flex items-center justify-center w-7 h-7 rounded-md text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-grab active:cursor-grabbing select-none"
                title="Drag to reorder"
            >
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                    <circle cx="9"  cy="6"  r="1.5" />
                    <circle cx="15" cy="6"  r="1.5" />
                    <circle cx="9"  cy="12" r="1.5" />
                    <circle cx="15" cy="12" r="1.5" />
                    <circle cx="9"  cy="18" r="1.5" />
                    <circle cx="15" cy="18" r="1.5" />
                </svg>
            </div>
        </td>

        <td class="px-4 py-3">
            <div class="w-14 h-10 rounded bg-gray-100 dark:bg-gray-800 overflow-hidden">
                <img v-if="row.cover_image_url" :src="row.cover_image_url" alt="" class="w-full h-full object-cover" />
            </div>
        </td>

        <td class="px-4 py-3 font-semibold">{{ row.name }}</td>

        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ row.type_label }}</td>

        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ row.barangay_label }}</td>

        <td class="px-4 py-3 font-medium">{{ formattedPrice }}</td>

        <td class="px-4 py-3">
            <div class="flex items-center gap-1 justify-center">
                <a :href="`/listings/${row.uuid}`"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer"
                    title="View public listing in new tab">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                        <circle cx="12" cy="12" r="3" />
                    </svg>
                    View
                </a>
                <a :href="`/admin/listings/${row.uuid}/edit?from=featured`"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer"
                    title="Edit">
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
