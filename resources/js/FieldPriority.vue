<script setup>
import { ref, computed } from 'vue';
import axios from './axios';
import FieldSidebar from './components/field/FieldSidebar.vue';
import FieldTopBar from './components/field/FieldTopBar.vue';
import FieldPriorityListingsTable from './components/field/FieldPriorityListingsTable.vue';

const initial = window.__INITIAL_FIELD_PRIORITY__ ?? {};

const user = ref(initial.user ?? {
    name:        'Field Officer',
    initials:    'FO',
    role_label:  'Field Officer',
    permissions: [],
});

const rows = ref(initial.priority?.data ?? []);
let lastCommittedOrder = rows.value.map((r) => r.uuid);

const saving = ref(false);
let saveTimer = null;

const isEmpty = computed(() => rows.value.length === 0);

const subtitle = computed(() => {
    const n = rows.value.length;
    return n === 0
        ? 'No priority listings yet'
        : `${n} priority ${n === 1 ? 'listing' : 'listings'} · drag to reorder`;
});

function scheduleSave() {
    if (saveTimer) clearTimeout(saveTimer);
    saveTimer = setTimeout(commitOrder, 800);
}

async function commitOrder() {
    const order = rows.value.map((r) => r.uuid);
    saving.value = true;
    try {
        await axios.put('/field/api/listings/priority-sort', { order });
        lastCommittedOrder = order;
    } catch (err) {
        const uuidToRow = new Map(rows.value.map((r) => [r.uuid, r]));
        rows.value = lastCommittedOrder.map((uuid) => uuidToRow.get(uuid)).filter(Boolean);

        const message = err?.response?.data?.message
            ?? 'Could not save the new order.';
        window.dispatchEvent(new CustomEvent('admin-toast', {
            detail: { type: 'error', message },
        }));
    } finally {
        saving.value = false;
    }
}

function onReorder(newList) {
    rows.value = newList;
    scheduleSave();
}

function onRemoved(uuid) {
    rows.value = rows.value.filter((r) => r.uuid !== uuid);
    lastCommittedOrder = lastCommittedOrder.filter((u) => u !== uuid);
}
</script>

<template>
    <div class="h-screen flex overflow-hidden bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100">
        <FieldSidebar :user="user" />

        <div class="flex-1 flex flex-col min-w-0">
            <FieldTopBar title="Priority Listings" :subtitle="subtitle" />

            <main class="flex-1 overflow-y-auto px-6 pt-2 pb-6 lg:px-10 lg:pt-3 lg:pb-10">
                <div
                    v-if="isEmpty"
                    class="mt-6 rounded-lg border border-dashed border-gray-300 dark:border-gray-700 p-12 text-center"
                >
                    <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                    </svg>
                    <p class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                        No priority listings yet
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Mark a listing as priority from your queue to add it here.
                    </p>
                    <a
                        href="/field/listings"
                        class="inline-block mt-4 text-sm font-medium text-orange-600 dark:text-orange-400 hover:text-orange-700 dark:hover:text-orange-300"
                    >
                        Go to your queue →
                    </a>
                </div>

                <FieldPriorityListingsTable
                    v-else
                    class="mt-4"
                    :rows="rows"
                    :saving="saving"
                    @reorder="onReorder"
                    @removed="onRemoved"
                />
            </main>
        </div>
    </div>
</template>
