<script setup>
import { ref } from 'vue';
import FieldSidebar from './components/field/FieldSidebar.vue';
import FieldTopBar from './components/field/FieldTopBar.vue';
import FieldListingsTable from './components/field/FieldListingsTable.vue';

const initial = window.__INITIAL_FIELD_LISTINGS__ ?? {};

const user = ref(initial.user ?? {
    name:        'Field Officer',
    initials:    'FO',
    role_label:  'Field Officer',
    permissions: [],
});

const listings    = ref(initial.listings ?? []);
const q           = ref(initial.q ?? '');
const isSearching = ref(!!initial.isSearching);

// Search input — 1000ms grouped-debounce per CLAUDE.md UX pattern.
// Re-navigates to /field/listings?q=... so the controller renders the
// filtered set; no in-place AJAX filter (keeps the controller path simple
// and gives shareable URLs).
let debounceTimer = null;
function scheduleSubmit() {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        navigateToSearch();
    }, 1000);
}

function navigateToSearch() {
    if (debounceTimer) clearTimeout(debounceTimer);
    const params = new URLSearchParams();
    const trimmed = q.value.trim();
    if (trimmed !== '') params.set('q', trimmed);
    const target = '/field/listings' + (params.toString() ? '?' + params : '');
    window.location.assign(target);
}
</script>

<template>
    <div class="h-screen flex bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 overflow-hidden">
        <FieldSidebar :user="user" />
        <div class="flex-1 flex flex-col min-w-0">
            <FieldTopBar title="Listings" subtitle="Your assignments" />
            <main class="flex-1 overflow-y-auto p-6 lg:p-10">
                <!-- Search input -->
                <div class="mb-6">
                    <div class="relative max-w-md">
                        <svg
                            class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500 pointer-events-none"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <circle cx="11" cy="11" r="8" />
                            <path d="m21 21-4.35-4.35" />
                        </svg>
                        <input
                            v-model="q"
                            @input="scheduleSubmit"
                            @keydown.enter.prevent="navigateToSearch"
                            type="text"
                            placeholder="Search title, barangay, directions, phone..."
                            class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-orange-500"
                        />
                    </div>
                    <p
                        v-if="isSearching"
                        class="mt-2 text-xs text-gray-500 dark:text-gray-400"
                    >
                        Showing search results — drag-to-reorder is paused while filtering.
                    </p>
                </div>

                <!-- Empty state -->
                <div
                    v-if="listings.length === 0"
                    class="text-center py-16 text-gray-500 dark:text-gray-400"
                >
                    <p v-if="isSearching" class="text-sm">No matches for "{{ q }}".</p>
                    <template v-else>
                        <p class="text-lg font-medium mb-1">No assignments yet.</p>
                        <p class="text-sm">The desk team will assign listings to you here.</p>
                    </template>
                </div>

                <!-- Table -->
                <FieldListingsTable
                    v-else
                    :listings="listings"
                    :is-searching="isSearching"
                />
            </main>
        </div>
    </div>
</template>
