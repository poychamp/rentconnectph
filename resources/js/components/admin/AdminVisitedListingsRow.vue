<script setup>
import { computed } from 'vue';

const props = defineProps({
    row: { type: Object, required: true },
});

// Format E.164 +639XXXXXXXXX as 0917 123 4567 for at-a-glance dialing.
const formattedContactPhone = computed(() => {
    const phone = props.row.contact_phone;
    if (!phone) return null;
    const digits = String(phone).replace(/\D/g, '');
    if (digits.length === 12 && digits.startsWith('63')) {
        return '0' + digits.slice(2, 5) + ' ' + digits.slice(5, 8) + ' ' + digits.slice(8);
    }
    return phone;
});

const dateTimeFormatter = new Intl.DateTimeFormat('en-US', {
    year: 'numeric', month: 'short', day: '2-digit',
    hour: '2-digit', minute: '2-digit',
});

const formattedVisitedAt = computed(() => {
    if (!props.row.visited_at) return '—';
    return dateTimeFormatter.format(new Date(props.row.visited_at));
});
</script>

<template>
    <tr class="text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/40">
        <td class="px-4 py-3 font-semibold">{{ row.name }}</td>

        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ row.type_label }}</td>

        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ row.barangay_label }}</td>

        <td class="px-4 py-3 font-mono tabular-nums text-gray-700 dark:text-gray-200">{{ formattedContactPhone }}</td>

        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ row.assigned_to_name ?? '—' }}</td>

        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ formattedVisitedAt }}</td>

        <td class="px-4 py-3">
            <div class="flex items-center justify-center">
                <a :href="`/admin/listings/${row.uuid}/verify-edit`"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium text-emerald-700 dark:text-emerald-300 hover:bg-emerald-50 dark:hover:bg-emerald-950/30 cursor-pointer"
                    title="Verify">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                        <polyline points="22 4 12 14.01 9 11.01" />
                    </svg>
                    Verify
                </a>
            </div>
        </td>
    </tr>
</template>
