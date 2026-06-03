<script setup>
import { ref } from 'vue';
import AdminSidebar from './components/admin/AdminSidebar.vue';
import AdminTopBar from './components/admin/AdminTopBar.vue';
import AdminRejectedListingsToolbar from './components/admin/AdminRejectedListingsToolbar.vue';
import AdminRejectedListingsTable from './components/admin/AdminRejectedListingsTable.vue';
import AdminRejectedListingsPagination from './components/admin/AdminRejectedListingsPagination.vue';

const initialDashboard = window.__INITIAL_DASHBOARD__ ?? {};
const initialRejected  = window.__INITIAL_REJECTED__  ?? {
    data: [],
    meta: { current_page: 1, last_page: 1, total: 0, per_page: 10 },
};

const user = ref(initialDashboard.user ?? { name: 'Admin', initials: 'AD', role_label: 'Super Admin', permissions: [] });

const rows        = ref(initialRejected.data);
const currentPage = ref(initialRejected.meta.current_page);
const totalPages  = ref(initialRejected.meta.last_page);
const totalRows   = ref(initialRejected.meta.total);
const perPage     = ref(initialRejected.meta.per_page);

const bannerSubtitle = `${totalRows.value} rejected ${totalRows.value === 1 ? 'listing' : 'listings'}`;

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
            <AdminTopBar title="Rejected Listings" />
            <main class="flex-1 overflow-y-auto p-6 lg:p-8">
                <div class="bg-amber-200 dark:bg-amber-900/50 px-5 py-4 rounded-md flex items-center gap-4">
                    <svg class="w-8 h-8 shrink-0 text-amber-800 dark:text-amber-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10" />
                        <path d="m15 9-6 6M9 9l6 6" />
                    </svg>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-amber-800 dark:text-amber-200">
                            Rejected Listings
                        </h1>
                        <p class="text-sm text-amber-800/75 dark:text-amber-200/75 mt-0.5">
                            {{ bannerSubtitle }}
                        </p>
                    </div>
                </div>
                <AdminRejectedListingsToolbar class="mt-6" @search="onSearch" />
                <AdminRejectedListingsTable
                    class="mt-4"
                    :rows="rows"
                />
                <AdminRejectedListingsPagination
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
