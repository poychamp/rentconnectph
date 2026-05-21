<script setup>
import { ref } from 'vue';
import axios from './axios';
import AdminSidebar from './components/admin/AdminSidebar.vue';
import AdminTopBar from './components/admin/AdminTopBar.vue';
import AdminAmenitiesTabs from './components/admin/AdminAmenitiesTabs.vue';
import AdminAmenitiesTable from './components/admin/AdminAmenitiesTable.vue';

const initial = window.__INITIAL_ADMIN_AMENITIES__ ?? {
    user: null,
    amenities: [],
    counts: { active: 0, deleted: 0 },
};

const user = ref(initial.user ?? {
    name: 'Admin',
    initials: 'AD',
    role_label: 'Admin',
    permissions: [],
});

const rows = ref(initial.amenities);
const counts = ref(initial.counts);

let lastCommittedOrder = rows.value.map((r) => r.uuid);
const saving = ref(false);
let saveTimer = null;

function scheduleSave() {
    if (saveTimer) clearTimeout(saveTimer);
    saveTimer = setTimeout(commitOrder, 800);
}

async function commitOrder() {
    const order = rows.value.map((r) => r.uuid);
    saving.value = true;
    try {
        await axios.put('/admin/api/amenities/sort', { order });
        lastCommittedOrder = order;
    } catch (err) {
        // Revert to last committed order on error.
        const uuidToRow = new Map(rows.value.map((r) => [r.uuid, r]));
        rows.value = lastCommittedOrder.map((uuid) => uuidToRow.get(uuid)).filter(Boolean);

        const msg = err?.response?.data?.message
            ?? 'Could not save the new order. Please try again.';
        window.dispatchEvent(new CustomEvent('admin-toast', { detail: { type: 'error', message: msg } }));
    } finally {
        saving.value = false;
    }
}

function onReorder(newOrder) {
    rows.value = newOrder;
    scheduleSave();
}
</script>

<template>
    <div class="h-screen flex overflow-hidden bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100">
        <AdminSidebar :user="user" />
        <div class="flex-1 flex flex-col min-w-0">
            <AdminTopBar title="Amenities" />
            <main class="flex-1 overflow-y-auto p-6 lg:p-8">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                            Amenities
                        </h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Catalog of amenity tags shown on listings. Drag rows to reorder.
                        </p>
                    </div>
                    <a
                        href="/admin/amenities/create"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-orange-500 text-white text-sm font-semibold hover:bg-orange-600 active:bg-orange-700 transition cursor-pointer"
                    >
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 5v14M5 12h14" />
                        </svg>
                        Add Amenity
                    </a>
                </div>

                <AdminAmenitiesTabs active="active" :counts="counts" />

                <AdminAmenitiesTable
                    :rows="rows"
                    :saving="saving"
                    @reorder="onReorder"
                />
            </main>
        </div>
    </div>
</template>
