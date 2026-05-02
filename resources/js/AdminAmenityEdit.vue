<script setup>
import { ref } from 'vue';
import AdminSidebar from './components/admin/AdminSidebar.vue';
import AdminTopBar from './components/admin/AdminTopBar.vue';
import AdminAmenityForm from './components/admin/AdminAmenityForm.vue';

const initial = window.__INITIAL_ADMIN_AMENITY_EDIT__ ?? { user: null, amenity: null };

const user = ref(initial.user ?? {
    name: 'Admin',
    initials: 'AD',
    role_label: 'Admin',
    permissions: [],
});

const amenity = ref(initial.amenity ?? { uuid: '', name: '', slug: '', icon: '' });

const errors = ref(initial.errors ?? {});
const oldInput = ref(initial.oldInput ?? {
    name: amenity.value.name,
    slug: amenity.value.slug,
});
</script>

<template>
    <div class="h-screen flex overflow-hidden bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100">
        <AdminSidebar :user="user" />
        <div class="flex-1 flex flex-col min-w-0">
            <AdminTopBar title="Edit Amenity" />
            <main class="flex-1 overflow-y-auto p-6 lg:p-8">
                <div class="max-w-2xl mx-auto">
                    <div class="mb-6">
                        <a
                            href="/admin/amenities"
                            class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 mb-3"
                        >
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 12H5M12 19l-7-7 7-7" />
                            </svg>
                            Back to Amenities
                        </a>
                        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                            Edit Amenity
                        </h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Rename the display label. The slug auto-rederives from the name.
                        </p>
                    </div>

                    <AdminAmenityForm
                        :action="`/admin/amenities/${amenity.uuid}`"
                        method="PUT"
                        :initial="oldInput"
                        :errors="errors"
                        submit-label="Update Amenity"
                    />
                </div>
            </main>
        </div>
    </div>
</template>
