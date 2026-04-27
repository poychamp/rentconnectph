<script setup>
import AdminUnverifiedListingsRow from './AdminUnverifiedListingsRow.vue';

const props = defineProps({
    rows: { type: Array, required: true },
    currentSort: { type: String, default: 'created_at' },
    currentDir:  { type: String, default: 'asc' },
});

const emit = defineEmits(['sort']);

// Click on a sortable column: same column → toggle direction. Different column → start at desc.
function onSort(field) {
    const dir = props.currentSort === field && props.currentDir === 'desc' ? 'asc' : 'desc';
    emit('sort', { sort: field, dir });
}
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
                        <th class="px-4 py-3">
                            <button type="button" @click="onSort('created_at')"
                                class="inline-flex items-center gap-1 uppercase tracking-wider hover:text-gray-700 dark:hover:text-gray-200 cursor-pointer transition">
                                Added
                                <svg v-if="currentSort === 'created_at' && currentDir === 'asc'"
                                    class="w-3 h-3 text-orange-500" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 4l8 12H4z" />
                                </svg>
                                <svg v-else-if="currentSort === 'created_at' && currentDir === 'desc'"
                                    class="w-3 h-3 text-orange-500" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 20L4 8h16z" />
                                </svg>
                                <svg v-else class="w-3 h-3 text-gray-300 dark:text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="8 9 12 5 16 9" />
                                    <polyline points="8 15 12 19 16 15" />
                                </svg>
                            </button>
                        </th>
                        <th class="px-4 py-3">
                            <button type="button" @click="onSort('updated_at')"
                                class="inline-flex items-center gap-1 uppercase tracking-wider hover:text-gray-700 dark:hover:text-gray-200 cursor-pointer transition">
                                Updated
                                <svg v-if="currentSort === 'updated_at' && currentDir === 'asc'"
                                    class="w-3 h-3 text-orange-500" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 4l8 12H4z" />
                                </svg>
                                <svg v-else-if="currentSort === 'updated_at' && currentDir === 'desc'"
                                    class="w-3 h-3 text-orange-500" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 20L4 8h16z" />
                                </svg>
                                <svg v-else class="w-3 h-3 text-gray-300 dark:text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="8 9 12 5 16 9" />
                                    <polyline points="8 15 12 19 16 15" />
                                </svg>
                            </button>
                        </th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <AdminUnverifiedListingsRow
                        v-for="row in rows"
                        :key="row.id"
                        :row="row"
                    />
                </tbody>
            </table>
        </div>
    </div>
</template>
