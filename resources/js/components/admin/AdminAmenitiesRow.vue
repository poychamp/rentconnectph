<script setup>
import { ref } from 'vue';

defineProps({
    row: { type: Object, required: true },
});

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const showDeleteModal = ref(false);
const deleteFormEl = ref(null);

function openDeleteModal() {
    showDeleteModal.value = true;
}

function closeDeleteModal() {
    showDeleteModal.value = false;
}

function confirmDelete() {
    deleteFormEl.value?.submit();
}

// Duplicate of the public ListingDetailAmenities iconPaths map (no cross-domain
// import per CLAUDE.md). Unknown icon names fall back to `default` (tag glyph).
const iconPaths = {
    default:    'M20.59 13.41 12 22l-9-9V3h10l8.59 8.59a2 2 0 0 1 0 2.83ZM7 7h.01',
    droplet:    'M12 2.6S5 11 5 15a7 7 0 0 0 14 0c0-4-7-12.4-7-12.4Z',
    bolt:       'M13 2 3 14h8l-1 8 10-12h-8l1-8Z',
    wifi:       'M5 12.55a11 11 0 0 1 14 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01',
    car:        'M5 17h14M5 17a2 2 0 1 1 4 0M15 17a2 2 0 1 1 4 0M3 13h18l-2-6H5l-2 6Z',
    sofa:       'M3 11V7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4M3 11h18M3 11v6h18v-6M5 17v3M19 17v3',
    parking:    'M5 17h14M5 17a2 2 0 1 1 4 0M15 17a2 2 0 1 1 4 0M3 13h18l-2-6H5l-2 6Z',
    cigarette:  'M3 14h12v3H3z M16 14h4v3h-4z M5 10v-3 M9 10v-3 M13 10v-3',
    smoking:    'M3 14h12v3H3z M16 14h4v3h-4z M5 10v-3 M9 10v-3 M13 10v-3',
    no_smoking: 'M3 14h12v3H3z M16 14h4v3h-4z M5 10v-3 M9 10v-3 M13 10v-3 M2 18l20 -8',
    pets:       'M12 14 m-3 0 a3 3 0 1 0 6 0 a3 3 0 1 0 -6 0 M6 9 m-1.5 0 a1.5 1.5 0 1 0 3 0 a1.5 1.5 0 1 0 -3 0 M18 9 m-1.5 0 a1.5 1.5 0 1 0 3 0 a1.5 1.5 0 1 0 -3 0 M10 5 m-1 0 a1 1 0 1 0 2 0 a1 1 0 1 0 -2 0 M14 5 m-1 0 a1 1 0 1 0 2 0 a1 1 0 1 0 -2 0',
    no_pets:    'M12 14 m-3 0 a3 3 0 1 0 6 0 a3 3 0 1 0 -6 0 M6 9 m-1.5 0 a1.5 1.5 0 1 0 3 0 a1.5 1.5 0 1 0 -3 0 M18 9 m-1.5 0 a1.5 1.5 0 1 0 3 0 a1.5 1.5 0 1 0 -3 0 M10 5 m-1 0 a1 1 0 1 0 2 0 a1 1 0 1 0 -2 0 M14 5 m-1 0 a1 1 0 1 0 2 0 a1 1 0 1 0 -2 0 M2 22 l20 -20',
    aircon:     'M3 6h18v8H3z M3 11h18 M7 17v3 M12 17v3 M17 17v3',
};

</script>

<template>
    <tr class="text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/40">
        <td class="px-3 py-3 w-10">
            <div
                class="drag-handle inline-flex items-center justify-center w-7 h-7 rounded-md text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-grab active:cursor-grabbing select-none"
                title="Drag to reorder"
            >
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                    <circle cx="9"  cy="6"  r="1.5" />
                    <circle cx="15" cy="6"  r="1.5" />
                    <circle cx="9"  cy="12" r="1.5" />
                    <circle cx="15" cy="12" r="1.5" />
                    <circle cx="9"  cy="18" r="1.5" />
                    <circle cx="15" cy="18" r="1.5" />
                </svg>
            </div>
        </td>
        <td class="px-4 py-3 font-semibold">{{ row.name }}</td>
        <td class="px-4 py-3 font-mono text-xs text-gray-500 dark:text-gray-400">{{ row.slug }}</td>
        <td class="px-4 py-3">
            <div class="inline-flex items-center gap-2">
                <svg
                    class="w-4 h-4 text-orange-500"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path :d="iconPaths[row.icon] || iconPaths.default" />
                </svg>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ row.icon || '—' }}</span>
            </div>
        </td>
        <td class="px-4 py-3">
            <div class="flex items-center justify-center gap-1">
                <a
                    :href="`/admin/amenities/${row.uuid}/edit`"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer"
                    title="Edit"
                >
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 20h9" />
                        <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" />
                    </svg>
                    Edit
                </a>
                <form
                    ref="deleteFormEl"
                    :action="`/admin/amenities/${row.uuid}`"
                    method="POST"
                    class="inline"
                >
                    <input type="hidden" name="_token" :value="csrfToken">
                    <input type="hidden" name="_method" value="DELETE">
                    <button
                        type="button"
                        @click="openDeleteModal"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/30 cursor-pointer"
                        title="Delete"
                    >
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="3 6 5 6 21 6" />
                            <path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6" />
                            <path d="M10 11v6 M14 11v6" />
                            <path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2" />
                        </svg>
                        Delete
                    </button>
                </form>

                <Teleport to="body">
                    <div
                        v-if="showDeleteModal"
                        class="fixed inset-0 z-50 flex items-center justify-center p-4"
                        role="dialog"
                        aria-modal="true"
                    >
                        <div
                            class="absolute inset-0 bg-black/50"
                            @click="closeDeleteModal"
                        ></div>

                        <div class="relative w-full max-w-md rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-xl p-6">
                            <div class="flex items-start gap-4">
                                <div class="flex-shrink-0 inline-flex items-center justify-center w-10 h-10 rounded-full bg-rose-100 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400">
                                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="3 6 5 6 21 6" />
                                        <path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6" />
                                        <path d="M10 11v6 M14 11v6" />
                                        <path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2" />
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                        Delete amenity '{{ row.name }}'?
                                    </h3>
                                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                        The amenity moves to the Deleted tab. Listings keep their pivot links so a future Restore re-attaches them automatically. No listings lose data.
                                    </p>
                                </div>
                            </div>

                            <div class="mt-6 flex items-center justify-end gap-2">
                                <button
                                    type="button"
                                    @click="closeDeleteModal"
                                    class="px-4 py-2 rounded-md border border-gray-300 dark:border-gray-700 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="button"
                                    @click="confirmDelete"
                                    class="px-4 py-2 rounded-md bg-rose-600 hover:bg-rose-700 active:bg-rose-800 text-white text-sm font-semibold transition cursor-pointer"
                                >
                                    Delete Amenity
                                </button>
                            </div>
                        </div>
                    </div>
                </Teleport>
            </div>
        </td>
    </tr>
</template>
