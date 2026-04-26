<script setup>
import { ref, onMounted } from 'vue';

const isDark = ref(false);

onMounted(() => {
    isDark.value = document.documentElement.classList.contains('dark');
});

function toggle() {
    isDark.value = !isDark.value;
    document.documentElement.classList.toggle('dark', isDark.value);
    localStorage.setItem('theme', isDark.value ? 'dark' : 'light');
}
</script>

<template>
    <button
        type="button"
        @click="toggle"
        :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
        class="w-9 h-9 rounded-lg border border-transparent text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition flex items-center justify-center"
    >
        <svg
            v-if="isDark"
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.75"
            stroke-linecap="round"
            stroke-linejoin="round"
        >
            <circle cx="12" cy="12" r="4" />
            <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
        </svg>
        <svg
            v-else
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.75"
            stroke-linecap="round"
            stroke-linejoin="round"
        >
            <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z" />
        </svg>
    </button>
</template>
