<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    status: { type: [String, null], default: null },
});

const formEl = ref(null);

const isFiltered = computed(() => {
    return ['pending', 'sent', 'finalized', 'lost'].includes(props.status);
});

const selectedValue = computed(() => isFiltered.value ? props.status : '');

function onChange() {
    formEl.value?.submit();
}
</script>

<template>
    <form ref="formEl" action="/admin/leads" method="GET" class="mb-4 flex items-center gap-3">
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
        <select
            name="status"
            :value="selectedValue"
            @change="onChange"
            class="rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-white px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
        >
            <option value="">All statuses</option>
            <option value="pending">Pending</option>
            <option value="sent">Sent</option>
            <option value="finalized">Finalized</option>
            <option value="lost">Lost</option>
        </select>
        <a
            v-if="isFiltered"
            href="/admin/leads"
            class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 underline-offset-2 hover:underline"
        >
            Reset
        </a>
    </form>
</template>
