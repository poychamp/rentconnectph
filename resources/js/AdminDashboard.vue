<script setup>
import { ref, onMounted } from 'vue';
import AdminSidebar from './components/admin/AdminSidebar.vue';
import AdminTopBar from './components/admin/AdminTopBar.vue';
import AdminGreeting from './components/admin/AdminGreeting.vue';

const user = ref({
    name:        'Admin',
    initials:    'AD',
    role_label:  'Super Admin',
    permissions: [],
});

onMounted(() => {
    const initial = window.__INITIAL_DASHBOARD__?.user;
    if (initial) {
        user.value = initial;
    }
});
</script>

<template>
    <div class="min-h-screen flex bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100">
        <AdminSidebar :user="user" />
        <div class="flex-1 flex flex-col min-w-0">
            <AdminTopBar title="Dashboard" subtitle="Operations overview" />
            <main class="flex-1 p-6 lg:p-8">
                <AdminGreeting />
            </main>
        </div>
    </div>
</template>
