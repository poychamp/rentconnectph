<script setup>
import { ref, computed } from 'vue';
import axios from '../../axios';
import draggable from 'vuedraggable';

const props = defineProps({
    rows:   { type: Array,   required: true },
    saving: { type: Boolean, default: false },
});

const emit = defineEmits(['reorder', 'removed']);

const removingByUuid = ref({});
const isBusy = computed(() =>
    props.saving || Object.keys(removingByUuid.value).length > 0
);

function onUpdateModel(newList) {
    emit('reorder', newList);
}

async function removePriority(uuid) {
    if (removingByUuid.value[uuid]) return;
    removingByUuid.value[uuid] = true;

    try {
        const { data } = await axios.put(`/field/api/listings/${uuid}/priority-toggle`);
        if (!data.is_field_priority) {
            window.dispatchEvent(new CustomEvent('admin-toast', {
                detail: { type: 'success', message: data.message },
            }));
            emit('removed', uuid);
        }
    } catch (err) {
        const message = err.response?.data?.message ?? 'Could not remove from priority.';
        window.dispatchEvent(new CustomEvent('admin-toast', {
            detail: { type: 'error', message },
        }));
    } finally {
        delete removingByUuid.value[uuid];
    }
}
</script>

<template>
    <div class="relative bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div v-if="isBusy" class="absolute inset-x-0 top-0 h-0.5 overflow-hidden z-10">
            <div class="h-full w-1/3 bg-orange-500 dark:bg-orange-400 animate-[priority-loading-slide_1.2s_ease-in-out_infinite]"></div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-950/50 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="px-3 py-3 w-10"></th>
                        <th class="px-4 py-3 w-20">Photo</th>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Barangay</th>
                        <th class="px-4 py-3">Directions</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <draggable
                    tag="tbody"
                    :model-value="rows"
                    @update:model-value="onUpdateModel"
                    item-key="uuid"
                    handle=".drag-handle"
                    ghost-class="priority-ghost"
                    drag-class="priority-drag"
                    :animation="180"
                    :force-fallback="true"
                    :scroll-sensitivity="80"
                    :scroll-speed="14"
                    :bubble-scroll="true"
                >
                    <template #item="{ element: row }">
                        <tr class="text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/40">
                            <td class="px-3 py-3 drag-handle cursor-grab text-gray-400 dark:text-gray-500" title="Drag to reorder">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                                    <circle cx="9" cy="6" r="1.5"/>
                                    <circle cx="15" cy="6" r="1.5"/>
                                    <circle cx="9" cy="12" r="1.5"/>
                                    <circle cx="15" cy="12" r="1.5"/>
                                    <circle cx="9" cy="18" r="1.5"/>
                                    <circle cx="15" cy="18" r="1.5"/>
                                </svg>
                            </td>
                            <td class="px-4 py-3">
                                <div class="w-14 h-10 rounded overflow-hidden bg-gray-100 dark:bg-gray-800">
                                    <img v-if="row.display_image_url" :src="row.display_image_url" :alt="row.title" class="w-full h-full object-cover" loading="lazy">
                                </div>
                            </td>
                            <td class="px-4 py-3 font-semibold">{{ row.title }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ row.type_label }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ row.barangay_label }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ row.directions }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1 justify-center">
                                    <a
                                        :href="`/field/listings/${row.uuid}/request-verification?from=priority`"
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
                                        :href="`/field/listings/${row.uuid}/edit?from=priority`"
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
                                        :disabled="removingByUuid[row.uuid]"
                                        @click="removePriority(row.uuid)"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium text-amber-700 dark:text-amber-300 hover:bg-amber-50 dark:hover:bg-amber-950/30 cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
                                        title="Remove from priority"
                                    >
                                        <svg
                                            v-if="removingByUuid[row.uuid]"
                                            class="animate-spin w-4 h-4"
                                            viewBox="0 0 24 24" fill="none"
                                        >
                                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-opacity="0.25"/>
                                            <path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                                        </svg>
                                        <svg
                                            v-else
                                            class="w-4 h-4"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                        >
                                            <line x1="18" y1="6" x2="6" y2="18" />
                                            <line x1="6" y1="6" x2="18" y2="18" />
                                        </svg>
                                        Remove
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </draggable>
            </table>
        </div>
    </div>
</template>

<style>
@keyframes priority-loading-slide {
    0%   { transform: translateX(-100%); }
    50%  { transform: translateX(150%); }
    100% { transform: translateX(400%); }
}
.priority-ghost {
    opacity: 0.35;
}
.priority-drag {
    box-shadow: 0 8px 24px rgba(249, 115, 22, 0.25);
}
</style>
