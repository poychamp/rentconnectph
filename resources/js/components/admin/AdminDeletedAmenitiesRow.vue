<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    row: { type: Object, required: true },
});

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// Same iconPaths catalog as the Active row — duplicated per
// separate-files-over-shared-abstractions. Trashed rows still render the
// glyph so admins can identify what they're restoring.
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

const deletedAtFormatted = computed(() => {
    if (!props.row.deleted_at) return '—';
    return new Date(props.row.deleted_at).toLocaleDateString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric',
    });
});

const showRestoreModal = ref(false);
const restoreFormEl = ref(null);

function openRestoreModal() {
    showRestoreModal.value = true;
}

function closeRestoreModal() {
    showRestoreModal.value = false;
}

function confirmRestore() {
    restoreFormEl.value?.submit();
}
</script>

<template>
    <tr class="text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/40">
        <td class="px-4 py-3 font-semibold">{{ row.name }}</td>
        <td class="px-4 py-3 font-mono text-xs text-gray-500 dark:text-gray-400">{{ row.slug }}</td>
        <td class="px-4 py-3">
            <div class="inline-flex items-center gap-2">
                <svg
                    class="w-4 h-4 text-gray-400 dark:text-gray-500"
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
        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
            {{ deletedAtFormatted }}
        </td>
        <td class="px-4 py-3">
            <div class="flex items-center justify-center gap-1">
                <form
                    ref="restoreFormEl"
                    :action="`/admin/amenities/${row.uuid}/restore`"
                    method="POST"
                    class="inline"
                >
                    <input type="hidden" name="_token" :value="csrfToken">
                    <input type="hidden" name="_method" value="PUT">
                    <button
                        type="button"
                        @click="openRestoreModal"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/30 cursor-pointer"
                        title="Restore"
                    >
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 12a9 9 0 1 0 9-9" />
                            <polyline points="3 4 3 12 11 12" />
                        </svg>
                        Restore
                    </button>
                </form>
            </div>
        </td>
    </tr>

    <Teleport to="body">
        <div
            v-if="showRestoreModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
        >
            <div
                class="absolute inset-0 bg-black/50"
                @click="closeRestoreModal"
            ></div>

            <div class="relative w-full max-w-md rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-xl p-6">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 inline-flex items-center justify-center w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 12a9 9 0 1 0 9-9" />
                            <polyline points="3 4 3 12 11 12" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                            Restore amenity '{{ row.name }}'?
                        </h3>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            The amenity moves back to the Active tab. Listings that were tagged with it before deletion re-attach automatically (the pivot rows survived the soft delete).
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-2">
                    <button
                        type="button"
                        @click="closeRestoreModal"
                        class="px-4 py-2 rounded-md border border-gray-300 dark:border-gray-700 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="confirmRestore"
                        class="px-4 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold transition cursor-pointer"
                    >
                        Restore Amenity
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
