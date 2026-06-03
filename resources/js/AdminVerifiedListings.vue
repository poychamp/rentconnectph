<script setup>
import { ref } from 'vue';
import AdminSidebar from './components/admin/AdminSidebar.vue';
import AdminTopBar from './components/admin/AdminTopBar.vue';
import AdminVerifiedListingsToolbar from './components/admin/AdminVerifiedListingsToolbar.vue';
import AdminVerifiedListingsTable from './components/admin/AdminVerifiedListingsTable.vue';
import AdminVerifiedListingsPagination from './components/admin/AdminVerifiedListingsPagination.vue';

const initialDashboard = window.__INITIAL_DASHBOARD__ ?? {};
const initialVerified  = window.__INITIAL_VERIFIED__  ?? {
    data: [],
    meta: { current_page: 1, last_page: 1, total: 0, per_page: 10 },
};

const user = ref(initialDashboard.user ?? { name: 'Admin', initials: 'AD', role_label: 'Super Admin', permissions: [] });

const rows        = ref(initialVerified.data);
const currentPage = ref(initialVerified.meta.current_page);
const totalPages  = ref(initialVerified.meta.last_page);
const totalRows   = ref(initialVerified.meta.total);
const perPage     = ref(initialVerified.meta.per_page);

const bannerSubtitle = `${totalRows.value} verified ${totalRows.value === 1 ? 'listing' : 'listings'}`;

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
            <AdminTopBar title="Verified Listings" />
            <main class="flex-1 overflow-y-auto p-6 lg:p-8">
                <div class="bg-emerald-100 dark:bg-emerald-950/40 px-5 py-4 rounded-md flex items-center gap-4">
                    <svg class="w-8 h-8 shrink-0 text-emerald-700 dark:text-emerald-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 13c0 5-3.5 7.5-8 9-4.5-1.5-8-4-8-9V5l8-3 8 3v8z" />
                        <path d="M9 12l2 2 4-4" />
                    </svg>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-emerald-700 dark:text-emerald-300">
                            Verified Listings
                        </h1>
                        <p class="text-sm text-emerald-700/75 dark:text-emerald-300/75 mt-0.5">
                            {{ bannerSubtitle }}
                        </p>
                    </div>
                </div>
                <AdminVerifiedListingsToolbar class="mt-6" @search="onSearch" />
                <AdminVerifiedListingsTable class="mt-4" :rows="rows" />
                <AdminVerifiedListingsPagination
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
