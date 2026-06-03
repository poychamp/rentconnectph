<script setup>
defineProps({ deactivation: { type: Object, required: true } });

const formatDateTime = (iso) => iso
    ? new Intl.DateTimeFormat('en-US', { year: 'numeric', month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit' }).format(new Date(iso))
    : '—';
</script>

<template>
    <div class="rounded-lg border border-amber-200 dark:border-amber-900/60 bg-amber-50/50 dark:bg-amber-950/20 px-6 py-4">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            <div class="flex-1 text-sm text-amber-900 dark:text-amber-200">
                <p>
                    <span class="font-semibold">Deactivated on {{ formatDateTime(deactivation.deactivated_at) }}</span>
                    <template v-if="deactivation.deactivated_by"> by {{ deactivation.deactivated_by }}</template>.
                </p>
                <p v-if="deactivation.reason_label" class="mt-0.5">
                    <span class="font-medium">Reason:</span> {{ deactivation.reason_label }}
                </p>
                <p v-if="deactivation.notes" class="mt-0.5">
                    <span class="font-medium">Notes:</span> {{ deactivation.notes }}
                </p>
            </div>
        </div>
    </div>
</template>
