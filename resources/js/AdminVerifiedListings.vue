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

const user = ref(initialDashboard.user ?? { name: 'Admin', initials: 'AD', role_label: 'Super Admin' });

const rows        = ref(initialVerified.data);
const currentPage = ref(initialVerified.meta.current_page);
const totalPages  = ref(initialVerified.meta.last_page);
const totalRows   = ref(initialVerified.meta.total);
const perPage     = ref(initialVerified.meta.per_page);

const subtitle = `${totalRows.value} verified listings`;

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
            <AdminTopBar title="Verified Listings" :subtitle="subtitle" />
            <main class="flex-1 overflow-y-auto p-6 lg:p-8">
                <AdminVerifiedListingsToolbar @search="onSearch" />
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
