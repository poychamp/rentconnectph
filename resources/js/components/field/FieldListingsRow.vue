<script setup>
import { computed, ref } from 'vue';
import axios from '../../axios';

const props = defineProps({
    row: { type: Object, required: true },
    isSearching: { type: Boolean, default: false },
});

const dateTimeFormatter = new Intl.DateTimeFormat('en-US', {
    year: 'numeric', month: 'short', day: '2-digit',
    hour: '2-digit', minute: '2-digit',
});

const formattedAssignedAt = computed(() => {
    if (!props.row.assigned_at) return '';
    return dateTimeFormatter.format(new Date(props.row.assigned_at));
});

const togglingPriority = ref(false);

async function togglePriority() {
    if (togglingPriority.value) return;
    togglingPriority.value = true;

    try {
        const { data } = await axios.put(
            `/field/api/listings/${props.row.uuid}/priority-toggle`,
        );
        // Mutate the row's local state so the icon flips without a refetch.
        props.row.is_field_priority = data.is_field_priority;

        window.dispatchEvent(new CustomEvent('admin-toast', {
            detail: { type: 'success', message: data.message },
        }));
    } catch (err) {
        const message = err.response?.data?.message ?? 'Could not update priority.';
        window.dispatchEvent(new CustomEvent('admin-toast', {
            detail: { type: 'error', message },
        }));
    } finally {
        togglingPriority.value = false;
    }
}
</script>

<template>
    <tr class="text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/40">
        <td class="px-4 py-3 font-semibold">{{ row.title }}</td>

        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ row.type_label }}</td>

        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ row.barangay_label }}</td>

        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ row.directions }}</td>

        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ formattedAssignedAt }}</td>

        <td class="px-4 py-3">
            <div class="flex items-center gap-1 justify-center">
                <a
                    :href="`/field/listings/${row.uuid}/request-verification?from=listings`"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium text-emerald-700 dark:text-emerald-300 hover:bg-emerald-50 dark:hover:bg-emerald-950/30 cursor-pointer"
                    title="Request Verification"
                >
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                        <polyline points="22 4 12 14.01 9 11.01" />
                    </svg>
                    Verify
                </a>
                <a
                    :href="`/field/listings/${row.uuid}/edit?from=listings`"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer"
                    title="Edit"
                >
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 20h9" />
                        <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" />
                    </svg>
                    Edit
                </a>
                <button
                    type="button"
                    :disabled="togglingPriority"
                    @click="togglePriority"
                    :class="row.is_field_priority
                        ? 'inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/30 hover:bg-amber-100 dark:hover:bg-amber-950/50 cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed'
                        : 'inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium text-gray-600 dark:text-gray-300 hover:bg-amber-50 dark:hover:bg-amber-950/30 cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed'"
                    :title="row.is_field_priority ? 'In priority queue — click to remove' : 'Add to priority queue'"
                >
                    <svg
                        v-if="row.is_field_priority"
                        class="w-4 h-4"
                        viewBox="0 0 24 24"
                        fill="currentColor"
                    >
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                    </svg>
                    <svg
                        v-else
                        class="w-4 h-4"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                    </svg>
                    Priority
                </button>
            </div>
        </td>
    </tr>
</template>
