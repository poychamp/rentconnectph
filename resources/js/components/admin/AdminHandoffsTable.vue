<script setup>
import AdminHandoffsRow from './AdminHandoffsRow.vue';

defineProps({
    rows:         { type: Array,  required: true },
    emptyMessage: { type: String, required: true },
});
</script>

<template>
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div v-if="rows.length === 0" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
            {{ emptyMessage }}
        </div>
        <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-950/50 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Locked Listing</th>
                        <th class="px-4 py-3">Renter</th>
                        <th class="px-4 py-3">Created By</th>
                        <th class="px-4 py-3">
                            <span class="inline-flex items-center gap-1 uppercase tracking-wider">
                                Locked
                                <svg class="w-3 h-3 text-orange-500" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 4l8 12H4z" />
                                </svg>
                            </span>
                        </th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <AdminHandoffsRow v-for="row in rows" :key="row.id" :row="row" />
                </tbody>
            </table>
        </div>
    </div>
</template>
