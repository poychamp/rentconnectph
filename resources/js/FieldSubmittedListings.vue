<script setup>
import { ref, computed } from 'vue';
import FieldSidebar from './components/field/FieldSidebar.vue';
import FieldTopBar from './components/field/FieldTopBar.vue';
import FieldSubmittedListingsTable from './components/field/FieldSubmittedListingsTable.vue';

const initial = window.__INITIAL_FIELD_SUBMITTED_LISTINGS__ ?? {};

const user = ref(initial.user ?? {
    name:        'Field Officer',
    initials:    'FO',
    role_label:  'Field Officer',
    permissions: [],
});

const submitted   = ref(initial.submitted ?? { data: [], meta: {}, links: {} });
const q           = ref(initial.q ?? '');
const isSearching = ref(!!initial.isSearching);

const rows  = computed(() => submitted.value.data ?? []);
const meta  = computed(() => submitted.value.meta ?? {});
const links = computed(() => submitted.value.links ?? {});

// Search input — 1000ms grouped-debounce per CLAUDE.md UX pattern.
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
    const target = '/field/submitted-listings' + (params.toString() ? '?' + params : '');
    window.location.assign(target);
}

// Pagination href preserves the current ?q= via URLSearchParams.
function pageHref(page) {
    if (!page) return null;
    const params = new URLSearchParams(window.location.search);
    params.set('page', page);
    return '/field/submitted-listings?' + params.toString();
}
</script>

<template>
    <div class="h-screen flex bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 overflow-hidden">
        <FieldSidebar :user="user" />
        <div class="flex-1 flex flex-col min-w-0">
            <FieldTopBar title="Submitted" subtitle="Awaiting admin review" />
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
                            placeholder="Search title, barangay, directions..."
                            class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-orange-500"
                        />
                    </div>
                </div>

                <!-- Empty state A: zero submissions -->
                <div
                    v-if="rows.length === 0 && !isSearching"
                    class="text-center py-16 text-gray-500 dark:text-gray-400"
                >
                    <p class="text-lg font-medium mb-1">No submitted listings yet.</p>
                    <p class="text-sm mb-4">Once you request verification on a listing, it'll show up here.</p>
                    <a
                        href="/field/listings"
                        class="inline-block text-sm text-orange-600 dark:text-orange-400 hover:text-orange-700 dark:hover:text-orange-300 underline"
                    >
                        Go to your queue
                    </a>
                </div>

                <!-- Empty state B: zero search results -->
                <div
                    v-else-if="rows.length === 0 && isSearching"
                    class="text-center py-16 text-gray-500 dark:text-gray-400"
                >
                    <p class="text-sm">No submitted listings match "{{ q }}".</p>
                </div>

                <!-- Table + pagination -->
                <template v-else>
                    <FieldSubmittedListingsTable :rows="rows" />

                    <nav
                        v-if="meta.last_page > 1"
                        class="mt-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-sm"
                    >
                        <p class="text-gray-500 dark:text-gray-400">
                            Page {{ meta.current_page }} of {{ meta.last_page }}
                            <span class="text-gray-400 dark:text-gray-600">·</span>
                            {{ meta.total }} total
                        </p>
                        <div class="flex items-center gap-2">
                            <a
                                v-if="links.prev"
                                :href="pageHref(meta.current_page - 1)"
                                class="px-3 py-1.5 rounded-md border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-200"
                            >
                                Previous
                            </a>
                            <span
                                v-else
                                class="px-3 py-1.5 rounded-md border border-gray-200 dark:border-gray-800 text-gray-300 dark:text-gray-600"
                            >
                                Previous
                            </span>

                            <a
                                v-if="links.next"
                                :href="pageHref(meta.current_page + 1)"
                                class="px-3 py-1.5 rounded-md border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-200"
                            >
                                Next
                            </a>
                            <span
                                v-else
                                class="px-3 py-1.5 rounded-md border border-gray-200 dark:border-gray-800 text-gray-300 dark:text-gray-600"
                            >
                                Next
                            </span>
                        </div>
                    </nav>
                </template>
            </main>
        </div>
    </div>
</template>
