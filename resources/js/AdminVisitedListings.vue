<script setup>
import { ref } from 'vue';
import AdminSidebar from './components/admin/AdminSidebar.vue';
import AdminTopBar from './components/admin/AdminTopBar.vue';
import AdminVisitedListingsToolbar from './components/admin/AdminVisitedListingsToolbar.vue';
import AdminVisitedListingsTable from './components/admin/AdminVisitedListingsTable.vue';
import AdminVisitedListingsPagination from './components/admin/AdminVisitedListingsPagination.vue';

const initial = window.__INITIAL_VISITED_LISTINGS__ ?? {
    visited: { data: [], meta: { current_page: 1, last_page: 1, total: 0, per_page: 10 } },
};

const user = ref(initial.user ?? { name: 'Admin', initials: 'AD', role_label: 'Super Admin', permissions: [] });

const rows        = ref(initial.visited.data);
const currentPage = ref(initial.visited.meta.current_page);
const totalPages  = ref(initial.visited.meta.last_page);
const totalRows   = ref(initial.visited.meta.total);
const perPage     = ref(initial.visited.meta.per_page);

const bannerSubtitle = `${totalRows.value} ${totalRows.value === 1 ? 'listing' : 'listings'} awaiting verification`;

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
            <AdminTopBar title="Visited Listings" />
            <main class="flex-1 overflow-y-auto p-6 lg:p-8">
                <div class="bg-lime-100 dark:bg-lime-950/40 px-5 py-4 rounded-md flex items-center gap-4">
                    <svg class="w-8 h-8 shrink-0 text-lime-700 dark:text-lime-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                        <polyline points="22 4 12 14.01 9 11.01" />
                    </svg>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-lime-700 dark:text-lime-300">
                            Visited Listings
                        </h1>
                        <p class="text-sm text-lime-700/75 dark:text-lime-300/75 mt-0.5">
                            {{ bannerSubtitle }}
                        </p>
                    </div>
                </div>
                <AdminVisitedListingsToolbar class="mt-6" @search="onSearch" />
                <AdminVisitedListingsTable
                    class="mt-4"
                    :rows="rows"
                />
                <AdminVisitedListingsPagination
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
