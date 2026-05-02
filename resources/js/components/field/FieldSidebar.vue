<script setup>
import { computed } from 'vue';
import FieldLogo from './FieldLogo.vue';

const props = defineProps({
    user: {
        type: Object,
        required: true,
        validator: (u) =>
            u
            && typeof u.name === 'string'
            && typeof u.initials === 'string'
            && typeof u.role_label === 'string'
            && Array.isArray(u.permissions),
    },
});

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// Captured once at mount — site uses full page navigation (no SPA router) so pathname
// is fixed for the lifetime of this component.
const currentPath = window.location.pathname;

function isActive(item) {
    if (typeof item.matches === 'function') return item.matches(currentPath);
    if (!item.href || item.href === '#') return false;
    return currentPath === item.href;
}

// Mirrors AdminSidebar's userCan / Gate::before pattern: '*' short-circuits
// every check (super-admin gets ['*'] from AdminAuthUserResource).
function userCan(perm) {
    const list = props.user.permissions ?? [];
    return list.includes('*') || list.includes(perm);
}

const sections = [
    {
        label: 'Field',
        items: [
            {
                name: 'Dashboard',
                href: '/field',
                icon: 'home',
                matches: (path) => path === '/field' || path === '/field/',
            },
            {
                name: 'Queued Listings',
                href: '/field/listings',
                icon: 'list',
                requires: 'listings.field-work',
                matches: (path) => {
                    if (path === '/field/listings') return true;
                    if (/^\/field\/listings\/[^\/]+$/.test(path)) return true;
                    // Sub-routes (edit / request-verification / preview) —
                    // active when admin came from the queue (?from=listings).
                    if (/^\/field\/listings\/[^\/]+\/(edit|request-verification|preview)$/.test(path)) {
                        const from = new URLSearchParams(window.location.search).get('from');
                        return from === 'listings';
                    }
                    return false;
                },
            },
            {
                name: 'Priority',
                href: '/field/priority',
                icon: 'star',
                requires: 'listings.field-work',
                matches: (path) => {
                    if (path === '/field/priority') return true;
                    // Sub-routes — active when admin came from priority.
                    if (/^\/field\/listings\/[^\/]+\/(edit|request-verification|preview)$/.test(path)) {
                        const from = new URLSearchParams(window.location.search).get('from');
                        return from === 'priority';
                    }
                    return false;
                },
            },
            {
                name: 'Submitted',
                href: '/field/submitted-listings',
                icon: 'check-circle',
                requires: 'listings.field-work',
                matches: (path) => {
                    if (path === '/field/submitted-listings') return true;
                    // Sub-routes — active when admin came from submitted.
                    if (/^\/field\/listings\/[^\/]+\/(edit|request-verification|preview)$/.test(path)) {
                        const from = new URLSearchParams(window.location.search).get('from');
                        return from === 'submitted';
                    }
                    return false;
                },
            },
            {
                name: 'Verified',
                href: '/field/verified-listings',
                icon: 'shield-check',
                requires: 'listings.field-work',
                matches: (path) => {
                    if (path === '/field/verified-listings') return true;
                    // Sub-routes — active when admin came from verified.
                    if (/^\/field\/listings\/[^\/]+\/(edit|request-verification|preview)$/.test(path)) {
                        const from = new URLSearchParams(window.location.search).get('from');
                        return from === 'verified';
                    }
                    return false;
                },
            },
        ],
    },
];

const visibleSections = computed(() =>
    sections
        .map((s) => ({
            ...s,
            items: s.items.filter((i) => !i.requires || userCan(i.requires)),
        }))
        .filter((s) => s.items.length > 0),
);

const iconPaths = {
    home: 'M3 12 12 4l9 8M5 10v10h4v-6h6v6h4V10',
    list: 'M3 6h18M3 12h18M3 18h18',
    star: 'M12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26Z',
    'check-circle': 'M22 11.08V12a10 10 0 1 1-5.93-9.14M22 4 12 14.01l-3-3',
    'shield-check': 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z M9 12l2 2 4-4',
};
</script>

<template>
    <aside class="w-[220px] shrink-0 bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 flex flex-col">
        <!-- Header -->
        <div class="border-b border-gray-200 dark:border-gray-800 flex flex-col items-center" style="padding: 18px 16px 16px;">
            <a href="/field" class="hover:opacity-80 transition-opacity" title="Field home">
                <FieldLogo size="md" />
            </a>
            <p class="mt-1.5 text-[10.5px] font-semibold uppercase tracking-[0.08em] text-gray-400 dark:text-gray-500">
                Field Central
            </p>
        </div>

        <!-- Nav -->
        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-6">
            <div v-for="section in visibleSections" :key="section.label">
                <p class="px-2 mb-1 text-[10px] font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">
                    {{ section.label }}
                </p>
                <ul class="space-y-0.5">
                    <li v-for="item in section.items" :key="item.name">
                        <a
                            :href="item.href"
                            :class="[
                                'group flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium transition',
                                isActive(item)
                                    ? 'bg-slate-900 text-white dark:bg-orange-500'
                                    : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800',
                            ]"
                        >
                            <svg
                                class="w-4 h-4 shrink-0"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path :d="iconPaths[item.icon]" />
                            </svg>
                            <span class="truncate">{{ item.name }}</span>
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Footer (user) -->
        <div class="border-t border-gray-200 dark:border-gray-800 px-3 py-3 flex items-center gap-3">
            <div class="w-9 h-9 rounded-full bg-orange-500 text-white text-sm font-semibold flex items-center justify-center shrink-0">
                {{ user.initials }}
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ user.name }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ user.role_label }}</p>
            </div>
            <form action="/admin/logout" method="POST" class="inline">
                <input type="hidden" name="_token" :value="csrfToken">
                <button
                    type="submit"
                    class="p-1.5 rounded-md text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
                    title="Sign out"
                >
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" />
                    </svg>
                </button>
            </form>
        </div>
    </aside>
</template>
