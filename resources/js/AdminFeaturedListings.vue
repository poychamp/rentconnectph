<script setup>
import { ref, computed } from 'vue';
import axios from './axios';
import AdminSidebar from './components/admin/AdminSidebar.vue';
import AdminTopBar from './components/admin/AdminTopBar.vue';
import AdminFeaturedListingsTable from './components/admin/AdminFeaturedListingsTable.vue';

const initialDashboard = window.__INITIAL_DASHBOARD__ ?? {};
const initialFeatured  = window.__INITIAL_FEATURED__  ?? { data: [] };

const user = ref(initialDashboard.user ?? { name: 'Admin', initials: 'AD', role_label: 'Super Admin', permissions: [] });

const rows = ref(initialFeatured.data);
let lastCommittedOrder = rows.value.map((r) => r.uuid);

const saving = ref(false);
let saveTimer = null;

const bannerSubtitle = computed(() => {
    const n = rows.value.length;
    return `${n} featured ${n === 1 ? 'listing' : 'listings'}`;
});

function scheduleSave() {
    if (saveTimer) clearTimeout(saveTimer);
    saveTimer = setTimeout(commitOrder, 800);
}

async function commitOrder() {
    const order = rows.value.map((r) => r.uuid);
    saving.value = true;
    try {
        await axios.put('/admin/api/listings/featured-sort', { order });
        lastCommittedOrder = order;
    } catch (err) {
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
            <AdminTopBar title="Featured Listings" />
            <main class="flex-1 overflow-y-auto p-6 lg:p-8">
                <div class="bg-sky-100 dark:bg-sky-950/40 px-5 py-4 rounded-md flex items-center gap-4">
                    <svg class="w-8 h-8 shrink-0 text-sky-600 dark:text-sky-400" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                    </svg>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-sky-700 dark:text-sky-300">Featured Listings</h1>
                        <p class="text-sm text-sky-700/75 dark:text-sky-300/75 mt-0.5">{{ bannerSubtitle }}</p>
                    </div>
                </div>
                <AdminFeaturedListingsTable
                    class="mt-6"
                    :rows="rows"
                    :saving="saving"
                    @reorder="onReorder"
                />
            </main>
        </div>
    </div>
</template>
