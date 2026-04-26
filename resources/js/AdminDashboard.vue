<script setup>
import AdminSidebar from './components/admin/AdminSidebar.vue';
import AdminTopBar from './components/admin/AdminTopBar.vue';
import AdminGreeting from './components/admin/AdminGreeting.vue';

const placeholderUser = {
    name:       'Admin',
    first_name: 'Admin',
    initials:   'AD',
    role_label: 'Super Admin',
};
</script>

<template>
    <div class="min-h-screen flex bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100">
        <AdminSidebar :user="placeholderUser" />
        <div class="flex-1 flex flex-col min-w-0">
            <AdminTopBar title="Dashboard" subtitle="Operations overview" />
            <main class="flex-1 p-6 lg:p-10">
                <AdminGreeting :first-name="placeholderUser.first_name" />
            </main>
        </div>
    </div>
</template>
