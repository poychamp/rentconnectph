<script setup>
import { computed } from 'vue';

const props = defineProps({
    row: { type: Object, required: true },
});

const lockedAgeMs = computed(() => {
    if (!props.row.created_at) return 0;
    return Date.now() - new Date(props.row.created_at).getTime();
});

const lockedAgo = computed(() => {
    const diffMs = lockedAgeMs.value;
    const diffMin = Math.floor(diffMs / 60000);
    if (diffMin < 1) return 'just now';
    if (diffMin < 60) return `${diffMin}m ago`;
    const diffHr = Math.floor(diffMin / 60);
    if (diffHr < 24) return `${diffHr}h ago`;
    const diffDay = Math.floor(diffHr / 24);
    if (diffDay < 7) return `${diffDay}d ago`;
    return new Date(props.row.created_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
});

const lockedAbsolute = computed(() => {
    if (!props.row.created_at) return '';
    return new Date(props.row.created_at).toLocaleString('en-PH', {
        year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit',
    });
});

const ageBand = computed(() => {
    const min = lockedAgeMs.value / 60000;
    if (min < 15)      return { label: 'Fresh',             color: 'gray' };
    if (min < 60)      return { label: 'Recent',            color: 'amber' };
    if (min < 60 * 24) return { label: 'Ready to release',  color: 'emerald' };
    return                    { label: 'Stale',             color: 'red' };
});

const bandPillClass = computed(() => ({
    gray:    'bg-gray-100 dark:bg-gray-800/40 text-gray-700 dark:text-gray-300',
    amber:   'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300',
    emerald: 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300',
    red:     'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300',
}[ageBand.value.color]));

function toLocal(phone) {
    if (!phone || !phone.startsWith('+63')) return phone;
    return '0' + phone.slice(3);
}
</script>

<template>
    <tr class="text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/40">
        <td class="px-4 py-3">
            <a :href="`/listings/${row.listing.uuid}`" target="_blank" rel="noopener noreferrer"
                class="font-semibold text-gray-900 dark:text-white hover:text-orange-600 dark:hover:text-orange-400 inline-flex items-center gap-1.5">
                {{ row.listing.title }}
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
                    <polyline points="15 3 21 3 21 9" />
                    <line x1="10" y1="14" x2="21" y2="3" />
                </svg>
            </a>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ row.listing.barangay_label }}</p>
        </td>

        <td class="px-4 py-3">
            <div class="font-medium text-gray-900 dark:text-white">{{ row.renter.name }}</div>
            <div class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ toLocal(row.renter.phone) }}</div>
        </td>

        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ row.created_by_name }}</td>

        <td class="px-4 py-3">
            <div :title="lockedAbsolute" class="text-sm text-gray-700 dark:text-gray-300">{{ lockedAgo }}</div>
            <span :class="bandPillClass" class="inline-block mt-1 text-[10px] px-1.5 py-0.5 rounded-full font-semibold uppercase tracking-wider">
                {{ ageBand.label }}
            </span>
        </td>

        <td class="px-4 py-3">
            <div class="flex items-center justify-center">
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/30 cursor-pointer"
                    title="Release lock"
                >
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="5" y="11" width="14" height="10" rx="1" />
                        <path d="M8 11V7a4 4 0 0 1 7-2.5" />
                    </svg>
                    Release
                </button>
            </div>
        </td>
    </tr>
</template>
