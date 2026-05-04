<script setup>
import { ref } from 'vue';
import AdminSidebar from './components/admin/AdminSidebar.vue';
import AdminTopBar from './components/admin/AdminTopBar.vue';
import AdminHandoffsTable from './components/admin/AdminHandoffsTable.vue';
import AdminHandoffsPagination from './components/admin/AdminHandoffsPagination.vue';

const initial = window.__INITIAL_HANDOFFS__ ?? {
    user: null,
    handoffs: { data: [], meta: { current_page: 1, last_page: 1, total: 0, per_page: 10 }, links: {} },
};

const user = ref(initial.user ?? {
    name: 'Admin',
    initials: 'AD',
    role_label: 'Admin',
    permissions: [],
});

const rows = ref(initial.handoffs.data);
const meta = ref(initial.handoffs.meta);

function goToPage(page) {
    const params = new URLSearchParams(window.location.search);
    params.set('page', String(page));
    window.location.assign(`/admin/handoffs?${params.toString()}`);
}
</script>

<template>
    <div class="h-screen flex overflow-hidden bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100">
        <AdminSidebar :user="user" />
        <div class="flex-1 flex flex-col min-w-0">
            <AdminTopBar title="Handoffs" />
            <main class="flex-1 overflow-y-auto p-6 lg:p-8">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Handoffs
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Active locks. Oldest first. Release returns the listing to the inquiries queue.
                    </p>
                </div>

                <AdminHandoffsTable
                    :rows="rows"
                    empty-message="No active handoffs. Listings are unlocked."
                />

                <AdminHandoffsPagination
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
