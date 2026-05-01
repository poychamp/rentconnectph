<script setup>
import { computed } from 'vue';

const props = defineProps({ listing: { type: Object, required: true } });

const dateTimeFormatter = new Intl.DateTimeFormat('en-US', {
    year: 'numeric', month: 'short', day: '2-digit',
    hour: '2-digit', minute: '2-digit',
});

const formattedVisitedAt = computed(() => {
    if (!props.listing.visited_at) return '';
    return dateTimeFormatter.format(new Date(props.listing.visited_at));
});

const formattedVerifiedAt = computed(() => {
    if (!props.listing.verified_at) return '';
    return dateTimeFormatter.format(new Date(props.listing.verified_at));
});

const isVerified = computed(() => !!props.listing.is_verified);
</script>

<template>
    <!-- Verified state — emerald, "Verified by admin on [date]" -->
    <div
        v-if="isVerified && formattedVerifiedAt"
        class="rounded-lg border border-emerald-200 dark:border-emerald-900/60 bg-emerald-50/50 dark:bg-emerald-950/20 px-6 py-4"
    >
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                <polyline points="22 4 12 14.01 9 11.01" />
            </svg>
            <div class="flex-1 text-sm text-emerald-900 dark:text-emerald-200">
                <p><span class="font-semibold">Verified by admin on {{ formattedVerifiedAt }}.</span></p>
            </div>
        </div>
    </div>

    <!-- Submitted state — amber, "Visited on [date] — awaiting admin review" -->
    <div
        v-else-if="formattedVisitedAt"
        class="rounded-lg border border-amber-200 dark:border-amber-900/60 bg-amber-50/50 dark:bg-amber-950/20 px-6 py-4"
    >
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10" />
                <polyline points="12 6 12 12 16 14" />
            </svg>
            <div class="flex-1 text-sm text-amber-900 dark:text-amber-200">
                <p><span class="font-semibold">Visited on {{ formattedVisitedAt }}</span> — awaiting admin review.</p>
            </div>
        </div>
    </div>
</template>
