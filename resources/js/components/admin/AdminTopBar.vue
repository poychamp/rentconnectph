<script setup>
import AdminDarkModeToggle from './AdminDarkModeToggle.vue';

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    notificationCount: { type: Number, default: 3 },
});
</script>

<template>
    <header
        class="sticky top-0 z-10 h-14 shrink-0 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 flex items-center"
        style="padding: 0 24px;"
    >
        <div class="flex-1 min-w-0">
            <div class="font-semibold text-gray-900 dark:text-white" style="font-size: 15px; letter-spacing: -0.01em;">
                {{ title }}
            </div>
            <div
                v-if="subtitle"
                class="text-gray-400 dark:text-gray-500 truncate"
                style="font-size: 11.5px; margin-top: 1px;"
            >
                {{ subtitle }}
            </div>
        </div>

        <div class="flex items-center" style="gap: 6px;">
            <AdminDarkModeToggle />

            <button
                type="button"
                title="Notifications"
                class="relative w-9 h-9 rounded-lg border border-transparent text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition flex items-center justify-center"
            >
                <svg
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.75"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M6 8a6 6 0 0 1 12 0c0 5 2 6.5 2 6.5H4S6 13 6 8Z" />
                    <path d="M10 18a2 2 0 0 0 4 0" />
                </svg>
                <span
                    v-if="notificationCount > 0"
                    class="absolute bg-red-500 text-white font-bold leading-none flex items-center justify-center border-2 border-white dark:border-gray-900"
                    style="top: -2px; right: -2px; min-width: 16px; height: 16px; padding: 0 4px; border-radius: 8px; font-size: 10px;"
                >
                    {{ notificationCount }}
                </span>
            </button>
        </div>
    </header>
</template>
