<script setup>
import AdminInquiriesAllRow from './AdminInquiriesAllRow.vue';

defineProps({
    rows: { type: Array, required: true },
    emptyMessage: { type: String, required: true },
});
</script>

<template>
    <div v-if="rows.length === 0" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg p-12 text-center">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ emptyMessage }}
        </p>
    </div>
    <div v-else class="space-y-3">
        <AdminInquiriesAllRow
            v-for="row in rows"
            :key="row.uuid"
            :inquiry="row"
        />
    </div>
</template>
