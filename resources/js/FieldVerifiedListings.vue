<script setup>
import { ref, computed } from 'vue';
import FieldSidebar from './components/field/FieldSidebar.vue';
import FieldTopBar from './components/field/FieldTopBar.vue';
import FieldVerifiedListingsTable from './components/field/FieldVerifiedListingsTable.vue';
import FieldVerifiedListingsPagination from './components/field/FieldVerifiedListingsPagination.vue';

const initial = window.__INITIAL_FIELD_VERIFIED_LISTINGS__ ?? {};

const user = ref(initial.user ?? {
    name:        'Field Officer',
    initials:    'FO',
    role_label:  'Field Officer',
    permissions: [],
});

const verified    = ref(initial.verified ?? { data: [], meta: {}, links: {} });
const q           = ref(initial.q ?? '');
const isSearching = ref(!!initial.isSearching);

const rows  = computed(() => verified.value.data ?? []);
const meta  = computed(() => verified.value.meta ?? {});
const links = computed(() => verified.value.links ?? {});

let debounceTimer = null;
function scheduleSubmit() {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(navigateToSearch, 1000);
}

function navigateToSearch() {
    if (debounceTimer) clearTimeout(debounceTimer);
    const params = new URLSearchParams();
    const trimmed = q.value.trim();
    if (trimmed !== '') params.set('q', trimmed);
    const target = '/field/verified-listings' + (params.toString() ? '?' + params : '');
    window.location.assign(target);
}

function goToPage(page) {
    const params = new URLSearchParams(window.location.search);
    params.set('page', String(page));
    window.location.search = params.toString();
}
</script>

<template>
    <div class="h-screen flex bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 overflow-hidden">
        <FieldSidebar :user="user" />
        <div class="flex-1 flex flex-col min-w-0">
            <FieldTopBar title="Verified" subtitle="Approved by admin" />
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
                            type="search"
                            placeholder="Search title, barangay, directions…"
                            class="w-full rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 pl-10 pr-3 py-2 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none"
                        />
                    </div>
                </div>

                <!-- Empty state A: zero verified -->
                <div
                    v-if="rows.length === 0 && !isSearching"
                    class="text-center py-16 text-gray-500 dark:text-gray-400"
                >
                    <p class="text-lg font-medium mb-1">No verified listings yet.</p>
                    <p class="text-sm mb-4">Once admin verifies your submissions, they'll show up here.</p>
                    <a
                        href="/field/submitted-listings"
                        class="inline-block text-sm text-orange-600 dark:text-orange-400 hover:text-orange-700 dark:hover:text-orange-300 underline"
                    >
                        Go to your submitted listings
                    </a>
                </div>

                <!-- Empty state B: zero search results -->
                <div
                    v-else-if="rows.length === 0 && isSearching"
                    class="text-center py-16 text-gray-500 dark:text-gray-400"
                >
                    <p class="text-sm">No verified listings match "{{ q }}".</p>
                </div>

                <!-- Table + pagination -->
                <template v-else>
                    <FieldVerifiedListingsTable :rows="rows" />
                    <FieldVerifiedListingsPagination
                        :current-page="meta.current_page ?? 1"
                        :total-pages="meta.last_page ?? 1"
                        :total-rows="meta.total ?? 0"
                        :per-page="meta.per_page ?? 10"
                        @change="goToPage"
                    />
                </template>
            </main>
        </div>
    </div>
</template>
