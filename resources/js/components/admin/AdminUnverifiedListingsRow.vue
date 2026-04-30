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

const formattedDate = computed(() =>
    dateTimeFormatter.format(new Date(props.row.created_at))
);

const formattedUpdatedAt = computed(() =>
    dateTimeFormatter.format(new Date(props.row.updated_at))
);

// prequal_status → pill color. Mirrors the label catalog in App\Enums\PrequalStatus
// (not_called / called_yes / no_answer). Legacy rows have null status → no pill.
const prequalPillClass = computed(() => {
    switch (props.row.prequal_status) {
        case 'called_yes':
            return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300';
        case 'no_answer':
            return 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300';
        case 'not_called':
        default:
            return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300';
    }
});
</script>

<template>
    <tr class="text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/40">
        <td class="px-4 py-3 font-semibold">{{ row.name }}</td>

        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ row.type_label }}</td>

        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ row.barangay_label }}</td>

        <td class="px-4 py-3 font-mono tabular-nums text-gray-700 dark:text-gray-200">{{ formattedContactPhone }}</td>

        <td class="px-4 py-3">
            <span v-if="row.prequal_status_label"
                :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium', prequalPillClass]">
                {{ row.prequal_status_label }}
            </span>
            <div
                v-if="row.queue_status === 'assigned' && row.assigned_to_name"
                class="mt-1 text-[11px] text-gray-500 dark:text-gray-400"
            >
                Assigned to {{ row.assigned_to_name }}
            </div>
        </td>

        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ formattedDate }}</td>

        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ formattedUpdatedAt }}</td>

        <td class="px-4 py-3">
            <div class="flex items-center gap-1 justify-center">
                <a :href="`/admin/listings/${row.uuid}/unverified-edit`"
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
