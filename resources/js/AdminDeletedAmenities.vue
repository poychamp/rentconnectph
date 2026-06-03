<script setup>
import { ref } from 'vue';
import AdminSidebar from './components/admin/AdminSidebar.vue';
import AdminTopBar from './components/admin/AdminTopBar.vue';
import AdminAmenitiesTabs from './components/admin/AdminAmenitiesTabs.vue';
import AdminDeletedAmenitiesTable from './components/admin/AdminDeletedAmenitiesTable.vue';

const initial = window.__INITIAL_ADMIN_DELETED_AMENITIES__ ?? {
    user: null,
    amenities: [],
    counts: { active: 0, deleted: 0 },
};

const user = ref(initial.user ?? {
    name: 'Admin',
    initials: 'AD',
    role_label: 'Admin',
    permissions: [],
});

const rows = ref(initial.amenities);
const counts = ref(initial.counts);
</script>

<template>
    <div class="h-screen flex overflow-hidden bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100">
        <AdminSidebar :user="user" />
        <div class="flex-1 flex flex-col min-w-0">
            <AdminTopBar title="Deleted Amenities" />
            <main class="flex-1 overflow-y-auto p-6 lg:p-8">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Deleted Amenities
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Amenities that were soft-deleted. Restore brings them back to the Active tab; listings re-attach automatically.
                    </p>
                </div>

                <AdminAmenitiesTabs active="deleted" :counts="counts" />

                <AdminDeletedAmenitiesTable :rows="rows" />
            </main>
        </div>
    </div>
</template>
