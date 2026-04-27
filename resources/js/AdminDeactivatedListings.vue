<script setup>
import { ref } from 'vue';
import AdminSidebar from './components/admin/AdminSidebar.vue';
import AdminTopBar from './components/admin/AdminTopBar.vue';
import AdminDeactivatedListingsToolbar from './components/admin/AdminDeactivatedListingsToolbar.vue';
import AdminDeactivatedListingsTable from './components/admin/AdminDeactivatedListingsTable.vue';
import AdminDeactivatedListingsPagination from './components/admin/AdminDeactivatedListingsPagination.vue';

const initialDashboard   = window.__INITIAL_DASHBOARD__   ?? {};
const initialDeactivated = window.__INITIAL_DEACTIVATED__ ?? {
    data: [],
    meta: { current_page: 1, last_page: 1, total: 0, per_page: 10 },
};

const user = ref(initialDashboard.user ?? { name: 'Admin', initials: 'AD', role_label: 'Super Admin' });

const rows        = ref(initialDeactivated.data);
const currentPage = ref(initialDeactivated.meta.current_page);
const totalPages  = ref(initialDeactivated.meta.last_page);
const totalRows   = ref(initialDeactivated.meta.total);
const perPage     = ref(initialDeactivated.meta.per_page);

const bannerSubtitle = `${totalRows.value} deactivated ${totalRows.value === 1 ? 'listing' : 'listings'}`;

function goToPage(page) {
    const params = new URLSearchParams(window.location.search);
    params.set('page', String(page));
    window.location.search = params.toString();
}

function onSearch(q) {
    const params = new URLSearchParams(window.location.search);
    if (q === '') {
        params.delete('q');
    } else {
        params.set('q', q);
    }
    params.delete('page');
    window.location.search = params.toString();
}
</script>

<template>
    <div class="h-screen flex overflow-hidden bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100">
        <AdminSidebar :user="user" />
        <div class="flex-1 flex flex-col min-w-0">
            <AdminTopBar title="Deactivated Listings" />
            <main class="flex-1 overflow-y-auto p-6 lg:p-8">
                <div class="bg-red-100 dark:bg-red-950/40 px-5 py-4 rounded-md flex items-center gap-4">
                    <svg class="w-8 h-8 shrink-0 text-red-700 dark:text-red-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6" />
                        <path d="M19 6 17.5 20a2 2 0 0 1-2 2h-7a2 2 0 0 1-2-2L5 6M10 11v6M14 11v6M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2" />
                    </svg>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-red-700 dark:text-red-300">
                            Deactivated Listings
                        </h1>
                        <p class="text-sm text-red-700/75 dark:text-red-300/75 mt-0.5">
                            {{ bannerSubtitle }}
                        </p>
                    </div>
                </div>
                <AdminDeactivatedListingsToolbar class="mt-6" @search="onSearch" />
                <AdminDeactivatedListingsTable
                    class="mt-4"
                    :rows="rows"
                />
                <AdminDeactivatedListingsPagination
                    :current-page="currentPage"
                    :total-pages="totalPages"
                    :total-rows="totalRows"
                    :per-page="perPage"
                    @change="goToPage"
                />
            </main>
        </div>
    </div>
</template>
