<script setup>
import FieldListingsRow from './FieldListingsRow.vue';

defineProps({
    listings: { type: Array, required: true },
    isSearching: { type: Boolean, default: false },
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
                        <th class="px-4 py-3">Directions</th>
                        <th class="px-4 py-3">
                            <span class="inline-flex items-center gap-1 uppercase tracking-wider">
                                Assigned
                                <svg class="w-3 h-3 text-orange-500" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 4L4 16h16z" />
                                </svg>
                            </span>
                        </th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <FieldListingsRow
                        v-for="listing in listings"
                        :key="listing.uuid"
                        :row="listing"
                        :is-searching="isSearching"
                    />
                </tbody>
            </table>
        </div>
    </div>
</template>
