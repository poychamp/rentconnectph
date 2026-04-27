<script setup>
import AdminVerifiedListingsRow from './AdminVerifiedListingsRow.vue';

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
                        <th class="px-4 py-3">Price</th>
                        <th class="px-4 py-3">Verified</th>
                        <th class="px-4 py-3">Updated</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <AdminVerifiedListingsRow
                        v-for="row in rows"
                        :key="row.id"
                        :row="row"
                    />
                </tbody>
            </table>
        </div>
    </div>
</template>
