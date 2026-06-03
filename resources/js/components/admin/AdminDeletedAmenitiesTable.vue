<script setup>
import AdminDeletedAmenitiesRow from './AdminDeletedAmenitiesRow.vue';

defineProps({
    rows: { type: Array, required: true },
});
</script>

<template>
    <div
        v-if="rows.length === 0"
        class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-8 text-center"
    >
        <p class="text-sm text-gray-500 dark:text-gray-400">
            No deleted amenities. Deleted entries land here so you can restore them later.
        </p>
    </div>
    <div
        v-else
        class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm overflow-hidden"
    >
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-950/50 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Slug</th>
                        <th class="px-4 py-3">Icon</th>
                        <th class="px-4 py-3">Deleted</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <AdminDeletedAmenitiesRow
                        v-for="row in rows"
                        :key="row.uuid"
                        :row="row"
                    />
                </tbody>
            </table>
        </div>
    </div>
</template>
