<script setup>
defineProps({ rejection: { type: Object, required: true } });

const formatDateTime = (iso) => iso
    ? new Intl.DateTimeFormat('en-US', { year: 'numeric', month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit' }).format(new Date(iso))
    : '—';
</script>

<template>
    <div class="rounded-lg border border-amber-200 dark:border-amber-900/60 bg-amber-50/50 dark:bg-amber-950/20 px-6 py-4">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10" />
                <path d="m15 9-6 6M9 9l6 6" />
            </svg>
            <div class="flex-1 text-sm text-amber-900 dark:text-amber-200">
                <p>
                    <span class="font-semibold">Rejected on {{ formatDateTime(rejection.rejected_at) }}</span>
                    <template v-if="rejection.rejected_by"> by {{ rejection.rejected_by }}</template>.
                </p>
                <p v-if="rejection.reason_label" class="mt-0.5">
                    <span class="font-medium">Reason:</span> {{ rejection.reason_label }}
                </p>
                <p v-if="rejection.notes" class="mt-0.5">
                    <span class="font-medium">Notes:</span> {{ rejection.notes }}
                </p>
            </div>
        </div>
    </div>
</template>
