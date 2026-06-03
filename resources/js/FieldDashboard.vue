<script setup>
import { ref } from 'vue';
import FieldSidebar from './components/field/FieldSidebar.vue';
import FieldTopBar from './components/field/FieldTopBar.vue';
import FieldGreeting from './components/field/FieldGreeting.vue';

const initial = window.__INITIAL_FIELD_DASHBOARD__ ?? {};

const user = ref(initial.user ?? {
    name:        'Field Officer',
    initials:    'FO',
    role_label:  'Field Officer',
    permissions: [],
});
</script>

<template>
    <div class="min-h-screen flex bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100">
        <FieldSidebar :user="user" />
        <div class="flex-1 flex flex-col min-w-0">
            <FieldTopBar title="Dashboard" subtitle="Field operations" />
            <main class="flex-1 p-6 lg:p-8">
                <FieldGreeting />
            </main>
        </div>
    </div>
</template>
