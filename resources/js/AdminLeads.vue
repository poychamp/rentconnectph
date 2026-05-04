<script setup>
import { ref } from 'vue';
import AdminSidebar from './components/admin/AdminSidebar.vue';
import AdminTopBar from './components/admin/AdminTopBar.vue';
import AdminLeadsFilterBar from './components/admin/AdminLeadsFilterBar.vue';
import AdminLeadsTable from './components/admin/AdminLeadsTable.vue';
import AdminInquiriesPagination from './components/admin/AdminInquiriesPagination.vue';

const initial = window.__INITIAL_LEADS__ ?? {
    user:    null,
    leads:   { data: [], meta: { current_page: 1, last_page: 1, total: 0, per_page: 10 }, links: {} },
    filters: { status: null },
    errors:   null,
    oldInput: null,
};

const user = ref(initial.user ?? {
    name: 'Admin',
    initials: 'AD',
    role_label: 'Admin',
    permissions: [],
});

const rows     = ref(initial.leads.data);
const meta     = ref(initial.leads.meta);
const filters  = ref(initial.filters);
const errors   = ref(initial.errors   ?? {});
const oldInput = ref(initial.oldInput ?? {});

function goToPage(page) {
    const params = new URLSearchParams(window.location.search);
    params.set('page', String(page));
    window.location.assign(`/admin/leads?${params.toString()}`);
}
</script>

<template>
    <div class="h-screen flex overflow-hidden bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100">
        <AdminSidebar :user="user" />
        <div class="flex-1 flex flex-col min-w-0">
            <AdminTopBar title="Leads" />
            <main class="flex-1 overflow-y-auto p-6 lg:p-8">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Leads
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Broker pipeline. Newest first.
                    </p>
                </div>

                <AdminLeadsFilterBar :status="filters.status" />

                <AdminLeadsTable
                    :rows="rows"
                    :status-filter="filters.status"
                    :errors="errors"
                    :old-input="oldInput"
                    class="mb-6"
                />

                <AdminInquiriesPagination
                    :current-page="meta.current_page"
                    :total-pages="meta.last_page"
                    :total-rows="meta.total"
                    :per-page="meta.per_page"
                    @change="goToPage"
                />
            </main>
        </div>
    </div>
</template>
