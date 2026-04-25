<script setup>
import { ref, onMounted, watch } from 'vue';

const isDark = ref(false);

function applyTheme(dark) {
    document.documentElement.classList.toggle('dark', dark);
    localStorage.setItem('theme', dark ? 'dark' : 'light');
}

function toggle() {
    isDark.value = !isDark.value;
}

onMounted(() => {
    const saved = localStorage.getItem('theme');
    if (saved === 'dark') {
        isDark.value = true;
    } else if (saved === 'light') {
        isDark.value = false;
    } else {
        isDark.value = window.matchMedia('(prefers-color-scheme: dark)').matches;
    }
});

watch(isDark, applyTheme, { immediate: false });
</script>

<template>
    <button
        @click="toggle"
        :aria-label="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
        class="w-9 h-9 grid place-items-center rounded-full text-gray-600 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-300 dark:hover:text-white dark:hover:bg-gray-800 transition"
    >
        <svg v-if="isDark" class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 3a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0V4a1 1 0 0 1 1-1Zm0 15a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0v-1a1 1 0 0 1 1-1ZM4 12a1 1 0 0 1 1-1H4a1 1 0 1 1 0-2h1a1 1 0 0 1 0 2Zm15 0a1 1 0 0 1 1-1h1a1 1 0 1 1 0 2h-1a1 1 0 0 1-1-1ZM6.34 6.34a1 1 0 0 1 1.41 0l.71.7a1 1 0 1 1-1.41 1.42l-.71-.71a1 1 0 0 1 0-1.41Zm9.2 9.2a1 1 0 0 1 1.41 0l.71.71a1 1 0 1 1-1.41 1.41l-.71-.71a1 1 0 0 1 0-1.41ZM6.34 17.66a1 1 0 0 1 0-1.41l.71-.71a1 1 0 1 1 1.41 1.41l-.71.71a1 1 0 0 1-1.41 0Zm9.2-9.2a1 1 0 0 1 0-1.41l.71-.71a1 1 0 1 1 1.41 1.41l-.71.71a1 1 0 0 1-1.41 0ZM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10Z"/>
        </svg>
        <svg v-else class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
            <path d="M21.64 13a1 1 0 0 0-1.05-.14 8 8 0 0 1-3.24.68 8.05 8.05 0 0 1-7.9-6.59 8 8 0 0 1 .25-3.69 1 1 0 0 0-1.32-1.21A10 10 0 1 0 22 14.05a1 1 0 0 0-.36-1.05Z"/>
        </svg>
    </button>
</template>
