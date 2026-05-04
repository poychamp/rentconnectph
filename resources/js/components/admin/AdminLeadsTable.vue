<script setup>
import { computed } from 'vue';
import AdminLeadsRow from './AdminLeadsRow.vue';

const props = defineProps({
    rows:         { type: Array, required: true },
    statusFilter: { type: [String, null], default: null },
});

const labels = { pending: 'pending', sent: 'sent', finalized: 'finalized', lost: 'lost' };

const emptyMessage = computed(() => {
    if (props.statusFilter && labels[props.statusFilter]) {
        return `No leads with status '${labels[props.statusFilter]}'.`;
    }
    return 'No leads yet. Mark a handed-off inquiry as a lead from /admin/inquiries.';
});
</script>

<template>
    <div class="space-y-3">
        <div
            v-if="rows.length === 0"
            class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400"
        >
            {{ emptyMessage }}
        </div>
        <AdminLeadsRow
            v-for="row in rows"
            :key="row.uuid"
            :lead="row"
        />
    </div>
</template>
