<script setup>
import { computed } from 'vue';

const props = defineProps({
    assignedToName: { type: String, default: null },
    visitedAt:      { type: String, default: null },
});

const formattedVisitedAt = computed(() => {
    if (!props.visitedAt) return '';
    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric', month: 'short', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    }).format(new Date(props.visitedAt));
});
</script>

<template>
    <div class="rounded-lg border border-amber-200 dark:border-amber-900/60 bg-amber-50/50 dark:bg-amber-950/20 px-6 py-4">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10" />
                <polyline points="12 6 12 12 16 14" />
            </svg>
            <div class="flex-1 text-sm text-amber-900 dark:text-amber-200">
                <p>
                    <span class="font-semibold">{{ assignedToName || 'Field officer' }}</span>
                    submitted on
                    <span class="font-semibold">{{ formattedVisitedAt || '—' }}</span> — awaiting your review.
                </p>
            </div>
        </div>
    </div>
</template>
