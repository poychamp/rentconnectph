<script setup>
import { computed } from 'vue';
import AdminLogo from './AdminLogo.vue';

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

// Mirrors the server-side `Gate::before` super-admin bypass: a `'*'` entry in
// `permissions` short-circuits every check to true. See AdminAuthUserResource.
function userCan(perm) {
    const list = props.user.permissions ?? [];
    return list.includes('*') || list.includes(perm);
}

const sections = [
    {
        label: 'Operations',
        items: [
            {
                name: 'Dashboard',
                href: '/admin',
                icon: 'home',
                requires: 'admin.access',
                matches: (path) => path === '/admin' || path === '/admin/',
            },
        ],
    },
    {
        label: 'Listings',
        items: [
            { name: 'Add Listing',          href: '/admin/listings/create',         icon: 'plus',            requires: 'listings.manage' },
            {
                name: 'Unverified Listings',
                href: '/admin/unverified-listings',
                icon: 'shield-question',
                requires: 'listings.manage',
                matches: (path) => path === '/admin/unverified-listings'
                    || /^\/admin\/listings\/[^\/]+\/unverified-edit$/.test(path),
            },
            {
                name: 'Visited Listings',
                href: '/admin/visited-listings',
                icon: 'eye',
                requires: 'listings.manage',
                matches: (path) => path === '/admin/visited-listings'
                    || /^\/admin\/listings\/[^\/]+\/verify-edit$/.test(path),
            },
            {
                name: 'Verified Listings',
                href: '/admin/verified-listings',
                icon: 'shield-check',
                requires: 'listings.manage',
                matches: (path) => {
                    if (path === '/admin/verified-listings') return true;
                    // Edit sub-route — active when admin came from verified.
                    if (/^\/admin\/listings\/[^\/]+\/edit$/.test(path)) {
                        const from = new URLSearchParams(window.location.search).get('from');
                        return from === 'verified';
                    }
                    return false;
                },
            },
            {
                name: 'Featured Listings',
                href: '/admin/featured-listings',
                icon: 'star',
                requires: 'listings.manage',
                matches: (path) => {
                    if (path === '/admin/featured-listings') return true;
                    // Edit sub-route — active when admin came from featured.
                    if (/^\/admin\/listings\/[^\/]+\/edit$/.test(path)) {
                        const from = new URLSearchParams(window.location.search).get('from');
                        return from === 'featured';
                    }
                    return false;
                },
            },
            {
                name: 'Deactivated Listings',
                href: '/admin/deactivated-listings',
                icon: 'archive',
                requires: 'listings.manage',
                matches: (path) => path === '/admin/deactivated-listings'
                    || /^\/admin\/listings\/[^\/]+\/restore$/.test(path),
            },
            {
                name: 'Rejected Listings',
                href: '/admin/rejected-listings',
                icon: 'circle-x',
                requires: 'listings.manage',
                matches: (path) => path === '/admin/rejected-listings'
                    || /^\/admin\/listings\/[^\/]+\/reopen$/.test(path),
            },
        ],
    },
    {
        label: 'Inquiries',
        items: [
            {
                name: 'Inquiries',
                href: '/admin/filtered-inquiries',
                icon: 'mail',
                requires: 'inquiries.manage',
                matches: (path) => path.startsWith('/admin/filtered-inquiries')
                    || path.startsWith('/admin/inquiries'),
            },
            {
                name: 'Handoffs',
                href: '/admin/handoffs',
                icon: 'lock',
                requires: 'inquiries.manage',
                matches: (path) => path.startsWith('/admin/handoffs'),
            },
        ],
    },
    {
        label: 'Catalog',
        items: [
            {
                name: 'Amenities',
                href: '/admin/amenities',
                icon: 'tag',
                requires: 'amenities.manage',
                matches: (path) => path.startsWith('/admin/amenities')
                    || path.startsWith('/admin/deleted-amenities'),
            },
        ],
    },
    {
        label: 'Admin',
        items: [
            { name: 'Users',    href: '#', icon: 'user-cog', requires: 'users.manage' },
            { name: 'Settings', href: '#', icon: 'settings', requires: 'system.admin' },
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
    home:     'M3 12 12 4l9 8M5 10v10h4v-6h6v6h4V10',
    list:     'M3 6h18M3 12h18M3 18h18',
    plus:     'M12 5v14M5 12h14',
    queue:    'M3 5h18M3 12h18M3 19h18',
    users:    'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75',
    truck:    'M1 3h15v13H1zM16 8h4l3 3v5h-7zM5.5 21a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM18.5 21a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z',
    tag:      'M20.59 13.41 12 22l-9-9V3h10l8.59 8.59a2 2 0 0 1 0 2.83ZM7 7h.01',
    mail:     'M22 6l-10 7L2 6 M22 6v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6 M22 6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2',
    'shield-check': 'M20 13c0 5-3.5 7.5-8 9-4.5-1.5-8-4-8-9V5l8-3 8 3v8z M9 12l2 2 4-4',
    'star': 'M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z',
    'shield-question': 'M20 13c0 5-3.5 7.5-8 9-4.5-1.5-8-4-8-9V5l8-3 8 3v8z M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3 M12 17h.01',
    'eye': 'M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6z',
    'archive': 'M21 8v13H3V8 M1 3h22v5H1z M10 12h4',
    'circle-x': 'M2 12a10 10 0 1 0 20 0 10 10 0 0 0-20 0 M15 9l-6 6 M9 9l6 6',
    'lock':     'M5 11h14v10H5z M8 11V7a4 4 0 0 1 8 0v4',
    'user-cog': 'M12 13a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4 21v-2a4 4 0 0 1 4-4h4M19 14a3 3 0 1 0 0 6 3 3 0 0 0 0-6ZM19 8v2M19 18v2M22.4 9.6l-1.4 1.4M17 15l-1.4 1.4M22.4 18.4 21 17M17 13l-1.4-1.4',
    settings: 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h0a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h0a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v0a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z',
};
</script>

<template>
    <aside class="w-[220px] shrink-0 bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 flex flex-col">
        <!-- Header -->
        <div class="border-b border-gray-200 dark:border-gray-800 flex flex-col items-center" style="padding: 18px 16px 16px;">
            <a href="/admin" class="hover:opacity-80 transition-opacity" title="Admin home">
                <AdminLogo size="md" />
            </a>
            <p class="mt-1.5 text-[10.5px] font-semibold uppercase tracking-[0.08em] text-gray-400 dark:text-gray-500">
                Admin Central
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
