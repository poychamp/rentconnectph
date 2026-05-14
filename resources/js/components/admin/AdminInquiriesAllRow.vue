<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';

const props = defineProps({
    inquiry:  { type: Object, required: true },
    errors:   { type: Object, default: () => ({}) },
    oldInput: { type: Object, default: () => ({}) },
});

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

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
    const local = '0' + phone.slice(3);
    return `${local.slice(0, 4)} ${local.slice(4, 7)} ${local.slice(7)}`;
}

// ---------------------------------------------------------------------
// Mark as Lead — button + Teleport modal + form
// Visibility:
//   - Lead badge: inquiry.lead !== null (header slot)
//   - Mark as Lead button: inquiry.status === 'handed_off' && !inquiry.lead
//   - Otherwise: no lead-related affordance
// ---------------------------------------------------------------------

// Client-side validation re-enabled. Full UX bundle per
// feedback_client_validation_bundle.md: VALIDATORS map mirrors server
// rules, @blur fires validateLeadField, @focus fires clearLeadFieldError,
// submit calls validateAllLead which focuses the first invalid field on
// failure. Server (validateWithBag in named bag `lead-{uuid}`) stays as
// the final defense + canonical error messages.
const CLIENT_VALIDATION_ENABLED = true;

const leadUrl = computed(() => `/admin/inquiries/${props.inquiry.uuid}/lead`);

const leadCreatedAbsolute = computed(() => {
    if (!props.inquiry.lead?.created_at) return '';
    return new Date(props.inquiry.lead.created_at).toLocaleString('en-PH', {
        year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit',
    });
});

// Per-row error bag (named-bag pattern; one bag per inquiry uuid).
const myLeadBag = `lead-${props.inquiry.uuid}`;
const myLeadServerErrors = (props.errors && props.errors[myLeadBag]) ? props.errors[myLeadBag] : null;

// Pre-fill notes from oldInput when this row was the failed-submit target.
const leadForm = ref({
    notes: myLeadServerErrors ? (props.oldInput?.notes ?? '') : '',
});

const leadErrors = ref({});

const leadNotesRef = ref(null);
const leadFormEl   = ref(null);
const showLeadModal = ref(false);

const LEAD_VALIDATORS = {
    notes: (val) => (val ?? '').length > 2000
        ? 'Lead notes must be 2000 characters or fewer.'
        : null,
};

function validateLeadField(field) {
    if (!CLIENT_VALIDATION_ENABLED) return;
    const error = LEAD_VALIDATORS[field]?.(leadForm.value[field]);
    if (error) leadErrors.value[field] = error;
    else delete leadErrors.value[field];
}

function clearLeadFieldError(field) {
    delete leadErrors.value[field];
}

function validateAllLead() {
    if (!CLIENT_VALIDATION_ENABLED) return true;
    const next = {};
    for (const field of Object.keys(LEAD_VALIDATORS)) {
        const error = LEAD_VALIDATORS[field](leadForm.value[field]);
        if (error) next[field] = error;
    }
    leadErrors.value = next;
    return Object.keys(next).length === 0;
}

function counterClassLead(field) {
    return (leadForm.value[field] ?? '').length > 2000
        ? 'text-red-500 dark:text-red-400'
        : 'text-gray-500 dark:text-gray-400';
}

function counterTextLead(field) {
    return `${(leadForm.value[field] ?? '').length} / 2000`;
}

function openLeadModal()  { showLeadModal.value = true; }
function closeLeadModal() { showLeadModal.value = false; }

function submitLead() {
    if (!validateAllLead()) {
        nextTick(() => leadNotesRef.value?.focus());
        return;
    }
    leadFormEl.value?.submit();
}

// On mount: if this row was the target of a failed submit, populate the
// local error state from the named bag and auto-open the modal so the
// admin sees the error + their retained input. Handles both validation
// errors (notes.max) and state-guard errors (_state).
onMounted(() => {
    if (myLeadServerErrors) {
        for (const [field, messages] of Object.entries(myLeadServerErrors)) {
            leadErrors.value[field] = Array.isArray(messages) ? messages[0] : messages;
        }
        showLeadModal.value = true;
    }
});
</script>

<template>
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg p-5 hover:shadow-sm transition-shadow">
        <!-- Header: listing + status badge + Lead badge + submitted timestamp -->
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
                <span
                    v-if="inquiry.lead"
                    :title="`Lead created ${leadCreatedAbsolute}`"
                    class="inline-block text-[10px] px-1.5 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 font-semibold uppercase tracking-wider"
                >
                    Lead
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

        <!-- Mark as Lead action — handed_off rows that haven't been escalated yet -->
        <div
            v-if="inquiry.status === 'handed_off' && !inquiry.lead"
            class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-800 flex justify-end"
        >
            <form ref="leadFormEl" :action="leadUrl" method="POST" class="inline">
                <input type="hidden" name="_token" :value="csrfToken">
                <input type="hidden" name="notes" :value="leadForm.notes">
                <button
                    type="button"
                    @click="openLeadModal"
                    class="inline-flex items-center gap-1.5 px-4 py-1.5 text-xs font-semibold rounded-md bg-emerald-500 hover:bg-emerald-600 text-white transition cursor-pointer"
                >
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12l5 5L20 7" />
                    </svg>
                    Mark as Lead
                </button>
            </form>

            <Teleport to="body">
                <div
                    v-if="showLeadModal"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4"
                    role="dialog"
                    aria-modal="true"
                >
                    <div class="absolute inset-0 bg-black/50" @click="closeLeadModal"></div>

                    <div class="relative w-full max-w-lg rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-xl p-6 max-h-[90vh] overflow-y-auto">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0 inline-flex items-center justify-center w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M5 12l5 5L20 7" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                    Mark as Lead?
                                </h3>
                                <ul class="mt-2 text-sm text-gray-500 dark:text-gray-400 space-y-1 list-disc list-inside">
                                    <li>The inquiry will be escalated to a lead in <strong>Pending</strong> status.</li>
                                    <li>The listing remains locked from new inquiries while the broker works the deal.</li>
                                </ul>
                            </div>
                        </div>

                        <!-- State-guard error banner (race conditions / bypass attempts) -->
                        <div v-if="leadErrors._state" class="mt-4 rounded-md border border-red-200 dark:border-red-900/50 bg-red-50 dark:bg-red-900/20 p-3">
                            <p class="text-sm text-red-700 dark:text-red-300">
                                {{ leadErrors._state }}
                            </p>
                        </div>

                        <div class="mt-5">
                            <label for="lead-notes" class="block text-sm font-medium text-gray-900 dark:text-white">
                                Lead notes (optional)
                            </label>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                What was agreed? Move-in target, special arrangements, anything Marco should know.
                            </p>
                            <textarea
                                id="lead-notes"
                                ref="leadNotesRef"
                                v-model="leadForm.notes"
                                rows="4"
                                placeholder="Optional context for Marco…"
                                @blur="validateLeadField('notes')"
                                @focus="clearLeadFieldError('notes')"
                                class="mt-1.5 block w-full rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-white px-3 py-2 resize-y focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            ></textarea>
                            <div class="mt-1 flex items-center justify-between">
                                <p v-if="leadErrors.notes" class="text-xs text-red-600 dark:text-red-400">
                                    {{ leadErrors.notes }}
                                </p>
                                <p v-else class="text-xs text-gray-400 dark:text-gray-500">&nbsp;</p>
                                <p :class="counterClassLead('notes')" class="text-xs font-mono">
                                    {{ counterTextLead('notes') }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-6 flex items-center justify-end gap-2">
                            <button
                                type="button"
                                @click="closeLeadModal"
                                class="px-4 py-2 rounded-md border border-gray-300 dark:border-gray-700 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                @click="submitLead"
                                class="px-4 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold transition cursor-pointer"
                            >
                                Confirm Lead
                            </button>
                        </div>
                    </div>
                </div>
            </Teleport>
        </div>
    </div>
</template>
