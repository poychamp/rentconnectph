<script setup>
import { ref } from 'vue';
import AdminSidebar from './components/admin/AdminSidebar.vue';
import AdminTopBar from './components/admin/AdminTopBar.vue';
import AdminInquiriesTabs from './components/admin/AdminInquiriesTabs.vue';
import AdminInquiriesTable from './components/admin/AdminInquiriesTable.vue';
import AdminInquiriesPagination from './components/admin/AdminInquiriesPagination.vue';

const initial = window.__INITIAL_FILTERED_INQUIRIES__ ?? {
    user: null,
    inquiries: { data: [], meta: { current_page: 1, last_page: 1, total: 0, per_page: 10 }, links: {} },
    counts: { filtered: 0, all: 0 },
    errors: {},
    oldInput: {},
};

const user = ref(initial.user ?? {
    name: 'Admin',
    initials: 'AD',
    role_label: 'Admin',
    permissions: [],
});

const rows = ref(initial.inquiries.data);
const meta = ref(initial.inquiries.meta);
const counts = ref(initial.counts);
const errors = ref(initial.errors ?? {});
const oldInput = ref(initial.oldInput ?? {});

function goToPage(page) {
    const params = new URLSearchParams(window.location.search);
    params.set('page', String(page));
    window.location.assign(`/admin/filtered-inquiries?${params.toString()}`);
}
</script>

<template>
    <div class="h-screen flex overflow-hidden bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100">
        <AdminSidebar :user="user" />
        <div class="flex-1 flex flex-col min-w-0">
            <AdminTopBar title="Inquiries" />
            <main class="flex-1 overflow-y-auto p-6 lg:p-8">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Filtered Inquiries
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        New leads waiting for a qualifying call. Oldest first.
                    </p>
                </div>

                <AdminInquiriesTabs active="filtered" :counts="counts" />

                <AdminInquiriesTable
                    :rows="rows"
                    :errors="errors"
                    :old-input="oldInput"
                    empty-message="No new inquiries yet — renters will appear here when they submit."
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
