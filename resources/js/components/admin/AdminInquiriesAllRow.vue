<script setup>
import { computed } from 'vue';

const props = defineProps({
    inquiry: { type: Object, required: true },
});

const submittedAgo = computed(() => {
    if (!props.inquiry.submitted_at) return '';
    const then = new Date(props.inquiry.submitted_at);
    const now  = new Date();
    const diffMs = now - then;
    const diffMin = Math.floor(diffMs / 60000);
    if (diffMin < 1) return 'just now';
    if (diffMin < 60) return `${diffMin}m ago`;
    const diffHr = Math.floor(diffMin / 60);
    if (diffHr < 24) return `${diffHr}h ago`;
    const diffDay = Math.floor(diffHr / 24);
    if (diffDay < 7) return `${diffDay}d ago`;
    return then.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
});

const submittedAbsolute = computed(() => {
    if (!props.inquiry.submitted_at) return '';
    return new Date(props.inquiry.submitted_at).toLocaleString('en-PH', {
        year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit',
    });
});

const lifecycle = computed(() => {
    if (props.inquiry.status === 'handed_off') {
        return {
            label: 'Handed off',
            ts:    props.inquiry.handed_off_at,
            actor: props.inquiry.handed_off_by_name,
            color: 'emerald',
        };
    }
    if (props.inquiry.status === 'rejected') {
        return {
            label: 'Rejected',
            ts:    props.inquiry.rejected_at,
            actor: props.inquiry.rejected_by_name,
            color: 'rose',
        };
    }
    return null;
});

const lifecycleAbsolute = computed(() => {
    if (!lifecycle.value?.ts) return '';
    return new Date(lifecycle.value.ts).toLocaleString('en-PH', {
        year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit',
    });
});

const lifecycleDate = computed(() => {
    if (!lifecycle.value?.ts) return '';
    return new Date(lifecycle.value.ts).toLocaleDateString('en-PH', {
        year: 'numeric', month: 'short', day: 'numeric',
    });
});

const statusBadgeClass = computed(() => {
    const colors = {
        new:        'bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300',
        handed_off: 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300',
        rejected:   'bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300',
    };
    return colors[props.inquiry.status] ?? 'bg-gray-100 dark:bg-gray-800/40 text-gray-700 dark:text-gray-300';
});

const lifecycleLabelClass = computed(() => {
    if (!lifecycle.value) return '';
    return lifecycle.value.color === 'emerald'
        ? 'text-emerald-700 dark:text-emerald-400'
        : 'text-rose-700 dark:text-rose-400';
});

function toLocal(phone) {
    if (!phone || !phone.startsWith('+63')) return null;
    return '0' + phone.slice(3);
}
</script>

<template>
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg p-5 hover:shadow-sm transition-shadow">
        <!-- Header: listing + status badge + submitted timestamp -->
        <div class="flex items-start justify-between gap-4 mb-4">
            <div class="min-w-0 flex-1">
                <a
                    :href="`/listings/${inquiry.listing.uuid}`"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-base font-semibold text-gray-900 dark:text-white hover:text-orange-600 dark:hover:text-orange-400 transition truncate inline-flex items-center gap-1.5"
                >
                    {{ inquiry.listing.title }}
                    <svg class="w-3.5 h-3.5 opacity-60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14 21 3" />
                    </svg>
                </a>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ inquiry.listing.barangay_label }}
                </p>
            </div>
            <div class="flex flex-col items-end gap-1 shrink-0">
                <span :class="statusBadgeClass" class="text-[10px] px-1.5 py-0.5 rounded-full font-semibold uppercase tracking-wider">
                    {{ inquiry.status_label }}
                </span>
                <span :title="submittedAbsolute" class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                    {{ submittedAgo }}
                </span>
            </div>
        </div>

        <!-- Renter + listing-contact two-column -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Renter -->
            <div class="bg-orange-50 dark:bg-orange-900/10 border border-orange-100 dark:border-orange-900/30 rounded-md p-3">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-orange-700 dark:text-orange-400 mb-1">
                    Renter
                </p>
                <div class="flex items-center flex-wrap gap-2 mb-1">
                    <span class="text-sm font-medium text-gray-900 dark:text-white">
                        {{ inquiry.renter.name }}
                    </span>
                    <span
                        v-if="inquiry.renter.is_qualified === true"
                        class="inline-block text-[10px] px-1.5 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 font-semibold uppercase tracking-wider"
                        title="Renter has been qualified before — given owner contact via handoff"
                    >
                        Returning
                    </span>
                    <span
                        v-else-if="inquiry.renter.is_qualified === false"
                        class="inline-block text-[10px] px-1.5 py-0.5 rounded-full bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300 font-semibold uppercase tracking-wider"
                        title="Renter was explicitly disqualified on a prior reject"
                    >
                        Disqualified
                    </span>
                </div>
                <p class="font-mono text-sm text-orange-600 dark:text-orange-400">
                    {{ toLocal(inquiry.renter.phone) ?? inquiry.renter.phone }}
                </p>
                <p v-if="toLocal(inquiry.renter.phone)" class="font-mono text-xs text-orange-500/70 dark:text-orange-400/70">
                    {{ inquiry.renter.phone }}
                </p>

                <div v-if="inquiry.renter.notes" class="mt-3 pt-3 border-t border-orange-200 dark:border-orange-900/30">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-orange-700 dark:text-orange-400 mb-1">
                        Renter notes
                    </p>
                    <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-line">
                        {{ inquiry.renter.notes }}
                    </p>
                </div>
            </div>

            <!-- Listing contact -->
            <div class="bg-gray-50 dark:bg-gray-800/40 border border-gray-200 dark:border-gray-700 rounded-md p-3">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400 mb-1">
                    Listing contact
                </p>
                <div class="flex items-center gap-2 mb-1">
                    <span
                        v-if="inquiry.listing.contact_phone"
                        class="font-mono text-sm text-gray-900 dark:text-white"
                    >
                        {{ toLocal(inquiry.listing.contact_phone) ?? inquiry.listing.contact_phone }}
                    </span>
                    <span v-else class="text-sm text-gray-400 italic">No contact on file</span>
                    <span
                        v-if="inquiry.listing.contact_type_label"
                        class="text-[10px] px-1.5 py-0.5 rounded-full bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 font-medium"
                    >
                        {{ inquiry.listing.contact_type_label }}
                    </span>
                </div>
                <p v-if="toLocal(inquiry.listing.contact_phone)" class="font-mono text-xs text-gray-500 dark:text-gray-400">
                    {{ inquiry.listing.contact_phone }}
                </p>

                <div v-if="inquiry.listing.verification_notes" class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">
                        Verification notes
                    </p>
                    <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-line">
                        {{ inquiry.listing.verification_notes }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Lifecycle footer (handed_off + rejected only) -->
        <div v-if="lifecycle" class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-800">
            <p class="text-xs text-gray-500 dark:text-gray-400">
                <span class="font-semibold" :class="lifecycleLabelClass">{{ lifecycle.label }}</span>
                by <span class="font-medium text-gray-700 dark:text-gray-300">{{ lifecycle.actor ?? 'Deleted admin' }}</span>
                <span :title="lifecycleAbsolute"> · {{ lifecycleDate }}</span>
            </p>
            <div v-if="inquiry.notes" class="mt-2">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">
                    Notes from this event
                </p>
                <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-line">
                    {{ inquiry.notes }}
                </p>
            </div>
        </div>
    </div>
</template>
