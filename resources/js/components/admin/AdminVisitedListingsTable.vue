<script setup>
import AdminVisitedListingsRow from './AdminVisitedListingsRow.vue';

defineProps({
    rows: { type: Array, required: true },
});
</script>

<template>
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-950/50 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Barangay</th>
                        <th class="px-4 py-3">Phone</th>
                        <th class="px-4 py-3">Assigned to</th>
                        <th class="px-4 py-3">
                            <span class="inline-flex items-center gap-1 uppercase tracking-wider" title="Newest first">
                                Submitted
                                <svg class="w-3 h-3 text-orange-500" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 20L4 8h16z" />
                                </svg>
                            </span>
                        </th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <AdminVisitedListingsRow
                        v-for="row in rows"
                        :key="row.id"
                        :row="row"
                    />
                </tbody>
            </table>
        </div>
    </div>
</template>
