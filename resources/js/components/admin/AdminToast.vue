<script setup>
import { ref, onMounted } from 'vue';

// Reads window.__FLASH__ on mount and shows a toast for whichever flash key is set.
// Auto-dismisses after 4s. Click X to dismiss early.
// Supports: success (green check), error (red x), info (blue dot).

const visible = ref(false);
const message = ref('');
const type    = ref('success');

const TYPES = {
    success: { iconBg: 'bg-emerald-500', iconPath: 'm3 8 3.5 3.5L13 5' },
    error:   { iconBg: 'bg-red-500',     iconPath: 'M18 6 6 18M6 6l12 12' },
    info:    { iconBg: 'bg-blue-500',    iconPath: 'M12 16v-4M12 8h.01' },
};

onMounted(() => {
    const flash = window.__FLASH__ ?? {};

    // Pick the first non-null flash to show. Order: error → success → info.
    if (flash.error) {
        type.value = 'error';
        message.value = flash.error;
    } else if (flash.success) {
        type.value = 'success';
        message.value = flash.success;
    } else if (flash.info) {
        type.value = 'info';
        message.value = flash.info;
    } else {
        return;
    }

    visible.value = true;
    setTimeout(() => { visible.value = false; }, 4000);
});

function dismiss() {
    visible.value = false;
}
</script>

<template>
    <Transition
        enter-from-class="opacity-0 -translate-y-2"
        enter-active-class="transition duration-300 ease-out"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-2"
    >
        <div
            v-if="visible"
            class="fixed top-4 left-1/2 -translate-x-1/2 z-[100] max-w-sm bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg shadow-lg p-3.5 flex items-start gap-3"
        >
            <span :class="['w-5 h-5 rounded-full flex items-center justify-center shrink-0 mt-0.5', TYPES[type].iconBg]">
                <svg class="w-3 h-3 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                    <path :d="TYPES[type].iconPath" />
                </svg>
            </span>

            <p class="flex-1 text-sm text-gray-900 dark:text-white pt-px">{{ message }}</p>

            <button
                type="button"
                @click="dismiss"
                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer mt-px"
                title="Dismiss"
            >
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 6 6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </Transition>
</template>
