<script setup>
import { ref } from 'vue';
import AdminSidebar from './components/admin/AdminSidebar.vue';
import AdminTopBar from './components/admin/AdminTopBar.vue';
import AdminUnverifiedListingsToolbar from './components/admin/AdminUnverifiedListingsToolbar.vue';
import AdminUnverifiedListingsTable from './components/admin/AdminUnverifiedListingsTable.vue';
import AdminUnverifiedListingsPagination from './components/admin/AdminUnverifiedListingsPagination.vue';

const initialDashboard  = window.__INITIAL_DASHBOARD__  ?? {};
const initialUnverified = window.__INITIAL_UNVERIFIED__ ?? {
    data: [],
    meta: { current_page: 1, last_page: 1, total: 0, per_page: 10 },
};

const user = ref(initialDashboard.user ?? { name: 'Admin', initials: 'AD', role_label: 'Super Admin', permissions: [] });

const rows        = ref(initialUnverified.data);
const currentPage = ref(initialUnverified.meta.current_page);
const totalPages  = ref(initialUnverified.meta.last_page);
const totalRows   = ref(initialUnverified.meta.total);
const perPage     = ref(initialUnverified.meta.per_page);

const bannerSubtitle = `${totalRows.value} unverified ${totalRows.value === 1 ? 'listing' : 'listings'}`;

const initialUrlParams = new URLSearchParams(window.location.search);
const hasQuery = (initialUrlParams.get('q') ?? '').trim() !== '';
// When a search is active, sort isn't applied server-side (Scout path drops it)
// — clear the visual sort state so neither column shows an active arrow.
const currentSort = ref(hasQuery ? '' : (initialUrlParams.get('sort') ?? 'created_at'));
const currentDir  = ref(hasQuery ? '' : (initialUrlParams.get('dir')  ?? 'asc'));

function goToPage(page) {
    const params = new URLSearchParams(window.location.search);
    params.set('page', String(page));
    window.location.search = params.toString();
}

function onSearch(q) {
    // Any interaction with the search input drops sort/dir — sort and q are
    // mutually exclusive server-side (Algolia can't orderBy at query time).
    // Dropping unconditionally also means clearing the search returns to the
    // default sort instead of inheriting a stale sort from a previous URL.
    const params = new URLSearchParams(window.location.search);
    if (q === '') {
        params.delete('q');
    } else {
        params.set('q', q);
    }
    params.delete('sort');
    params.delete('dir');
    params.delete('page');
    window.location.search = params.toString();
}

function onSort({ sort, dir }) {
    // Sorting cancels any active search — same mutual-exclusion rule.
    const params = new URLSearchParams(window.location.search);
    params.set('sort', sort);
    params.set('dir', dir);
    params.delete('q');
    params.delete('page');
    window.location.search = params.toString();
}
</script>

<template>
    <div class="h-screen flex overflow-hidden bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100">
        <AdminSidebar :user="user" />
        <div class="flex-1 flex flex-col min-w-0">
            <AdminTopBar title="Unverified Listings" />
            <main class="flex-1 overflow-y-auto p-6 lg:p-8">
                <div class="bg-amber-100 dark:bg-amber-950/40 px-5 py-4 rounded-md flex items-center gap-4">
                    <svg class="w-8 h-8 shrink-0 text-amber-700 dark:text-amber-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 13c0 5-3.5 7.5-8 9-4.5-1.5-8-4-8-9V5l8-3 8 3v8z" />
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" />
                        <path d="M12 17h.01" />
                    </svg>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-amber-700 dark:text-amber-300">
                            Unverified Listings
                        </h1>
                        <p class="text-sm text-amber-700/75 dark:text-amber-300/75 mt-0.5">
                            {{ bannerSubtitle }}
                        </p>
                    </div>
                </div>
                <AdminUnverifiedListingsToolbar class="mt-6" @search="onSearch" />
                <AdminUnverifiedListingsTable
                    class="mt-4"
                    :rows="rows"
                    :current-sort="currentSort"
                    :current-dir="currentDir"
                    @sort="onSort"
                />
                <AdminUnverifiedListingsPagination
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
