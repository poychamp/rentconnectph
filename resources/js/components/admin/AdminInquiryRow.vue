<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';

const props = defineProps({
    inquiry:  { type: Object, required: true },
    errors:   { type: Object, default: () => ({}) },
    oldInput: { type: Object, default: () => ({}) },
});

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// Per-row error bag (named-bag pattern; one bag per inquiry uuid). If this
// row was the target of the failed submit, this object will hold the
// server-side errors keyed by field.
const myBag = `handoff-${props.inquiry.uuid}`;
const myServerErrors = (props.errors && props.errors[myBag]) ? props.errors[myBag] : null;

// Handoff form state. Pre-fill priority:
//   - If this row had errors on a prior submit → use oldInput (retain user input)
//   - Else, renter notes pre-fills from resource (returning-renter context),
//     inquiry notes empty (per-event, fresh).
const handoffForm = ref({
    notes:        myServerErrors ? (props.oldInput?.notes ?? '') : '',
    renter_notes: myServerErrors ? (props.oldInput?.renter_notes ?? '') : (props.inquiry.renter.notes ?? ''),
});

const handoffErrors = ref({});

const handoffNotesRef       = ref(null);
const handoffRenterNotesRef = ref(null);

// Client-side validation re-enabled after server-side error display +
// field-value retention smoke. Full bundle: VALIDATORS map mirrors server
// rules, @blur fires validateField, @focus fires clearFieldError, submit
// calls validateAll which focuses the first invalid field on failure.
// Server (validateWithBag) stays as the final defense.
const CLIENT_VALIDATION_ENABLED = true;

const VALIDATORS = {
    notes: (val) => (val ?? '').length > 2000
        ? 'Inquiry notes must be 2000 characters or fewer.'
        : null,
    renter_notes: (val) => (val ?? '').length > 2000
        ? 'Renter notes must be 2000 characters or fewer.'
        : null,
};

function validateField(field) {
    if (!CLIENT_VALIDATION_ENABLED) return;
    const error = VALIDATORS[field]?.(handoffForm.value[field]);
    if (error) handoffErrors.value[field] = error;
    else delete handoffErrors.value[field];
}

function clearFieldError(field) {
    delete handoffErrors.value[field];
}

function validateAll() {
    if (!CLIENT_VALIDATION_ENABLED) return true;
    const next = {};
    for (const field of Object.keys(VALIDATORS)) {
        const error = VALIDATORS[field](handoffForm.value[field]);
        if (error) next[field] = error;
    }
    handoffErrors.value = next;
    return Object.keys(next).length === 0;
}

function counterClass(field) {
    return (handoffForm.value[field] ?? '').length > 2000
        ? 'text-red-500 dark:text-red-400'
        : 'text-gray-500 dark:text-gray-400';
}

function counterText(field) {
    return `${(handoffForm.value[field] ?? '').length} / 2000`;
}

const submittedAgo = computed(() => {
    if (!props.inquiry.submitted_at) return '';
    const then = new Date(props.inquiry.submitted_at);
    const now = new Date();
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

const rejectUrl  = computed(() => `/admin/inquiries/${props.inquiry.uuid}/reject`);
const handoffUrl = computed(() => `/admin/inquiries/${props.inquiry.uuid}/handoff`);

function toLocal(phone) {
    if (!phone || !phone.startsWith('+63')) return null;
    return '0' + phone.slice(3);
}

const showRejectModal  = ref(false);
const showHandoffModal = ref(false);
const rejectFormEl     = ref(null);
const handoffFormEl    = ref(null);

function openRejectModal()   { showRejectModal.value = true; }
function closeRejectModal()  { showRejectModal.value = false; }
function submitReject()      { rejectFormEl.value?.submit(); }

function openHandoffModal()  { showHandoffModal.value = true; }
function closeHandoffModal() { showHandoffModal.value = false; }

// On mount: if this row was the target of the failed submit, populate
// handoffErrors from the named bag and auto-open the modal so the user
// sees their retained input + the server's complaint.
onMounted(() => {
    if (!myServerErrors) return;
    for (const [field, messages] of Object.entries(myServerErrors)) {
        handoffErrors.value[field] = Array.isArray(messages) ? messages[0] : messages;
    }
    showHandoffModal.value = true;
});

function submitHandoff() {
    if (!validateAll()) {
        nextTick(() => {
            if (handoffErrors.value.renter_notes) {
                handoffRenterNotesRef.value?.focus();
            } else if (handoffErrors.value.notes) {
                handoffNotesRef.value?.focus();
            }
        });
        return;
    }
    handoffFormEl.value?.submit();
}
</script>

<template>
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg p-5 hover:shadow-sm transition-shadow">
        <!-- Header: listing + submitted -->
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
            <span
                :title="submittedAbsolute"
                class="text-xs text-gray-500 dark:text-gray-400 shrink-0 whitespace-nowrap"
            >
                {{ submittedAgo }}
            </span>
        </div>

        <!-- Renter — the person to call -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-orange-50 dark:bg-orange-900/10 border border-orange-100 dark:border-orange-900/30 rounded-md p-3">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-orange-700 dark:text-orange-400 mb-1">
                    Call this renter
                </p>
                <div class="flex items-center flex-wrap gap-2 mb-1">
                    <span class="text-sm font-medium text-gray-900 dark:text-white">
                        {{ inquiry.renter.name }}
                    </span>
                    <span
                        v-if="inquiry.renter.is_qualified"
                        class="inline-block text-[10px] px-1.5 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 font-semibold uppercase tracking-wider"
                        title="Renter has been qualified before — given owner contact via handoff"
                    >
                        Returning
                    </span>
                    <span
                        v-if="inquiry.renter.prior_rejected_count > 0"
                        class="inline-block text-[10px] px-1.5 py-0.5 rounded-full bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300 font-semibold uppercase tracking-wider"
                        :title="`Previously rejected ${inquiry.renter.prior_rejected_count} time${inquiry.renter.prior_rejected_count > 1 ? 's' : ''}`"
                    >
                        Rejected{{ inquiry.renter.prior_rejected_count > 1 ? ` ×${inquiry.renter.prior_rejected_count}` : '' }}
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

            <!-- Owner contact — what to hand over once qualified -->
            <div class="bg-gray-50 dark:bg-gray-800/40 border border-gray-200 dark:border-gray-700 rounded-md p-3">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400 mb-1">
                    Hand over (once qualified)
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

        <!-- Actions -->
        <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-800 flex justify-end gap-2">
            <form ref="rejectFormEl" :action="rejectUrl" method="POST" class="inline">
                <input type="hidden" name="_token" :value="csrfToken">
                <input type="hidden" name="_method" value="PUT">
                <button
                    type="button"
                    @click="openRejectModal"
                    class="px-3 py-1.5 text-xs font-semibold rounded-md border border-red-200 dark:border-red-900/50 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition cursor-pointer"
                >
                    Reject
                </button>
            </form>
            <form ref="handoffFormEl" :action="handoffUrl" method="POST" class="inline">
                <input type="hidden" name="_token" :value="csrfToken">
                <input type="hidden" name="_method" value="PUT">
                <input type="hidden" name="notes" :value="handoffForm.notes">
                <input type="hidden" name="renter_notes" :value="handoffForm.renter_notes">
                <button
                    type="button"
                    @click="openHandoffModal"
                    class="px-4 py-1.5 text-xs font-semibold rounded-md bg-emerald-500 hover:bg-emerald-600 text-white transition cursor-pointer"
                >
                    Handoff
                </button>
            </form>

            <Teleport to="body">
                <div
                    v-if="showRejectModal"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4"
                    role="dialog"
                    aria-modal="true"
                >
                    <div class="absolute inset-0 bg-black/50" @click="closeRejectModal"></div>

                    <div class="relative w-full max-w-md rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-xl p-6">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0 inline-flex items-center justify-center w-10 h-10 rounded-full bg-rose-100 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10" />
                                    <line x1="15" y1="9" x2="9" y2="15" />
                                    <line x1="9"  y1="9" x2="15" y2="15" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                    Reject inquiry from {{ inquiry.renter.name }}?
                                </h3>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                    The inquiry will be marked as dead and removed from the queue. The renter's prior-rejected count goes up by one.
                                </p>
                            </div>
                        </div>

                        <div class="mt-6 flex items-center justify-end gap-2">
                            <button
                                type="button"
                                @click="closeRejectModal"
                                class="px-4 py-2 rounded-md border border-gray-300 dark:border-gray-700 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                @click="submitReject"
                                class="px-4 py-2 rounded-md bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold transition cursor-pointer"
                            >
                                Reject Inquiry
                            </button>
                        </div>
                    </div>
                </div>
            </Teleport>

            <Teleport to="body">
                <div
                    v-if="showHandoffModal"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4"
                    role="dialog"
                    aria-modal="true"
                >
                    <div class="absolute inset-0 bg-black/50" @click="closeHandoffModal"></div>

                    <div class="relative w-full max-w-xl rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-xl p-6 max-h-[90vh] overflow-y-auto">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0 inline-flex items-center justify-center w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M5 12l5 5L20 7" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                    Confirm handoff for {{ inquiry.renter.name }}?
                                </h3>
                                <ul class="mt-2 text-sm text-gray-500 dark:text-gray-400 space-y-1 list-disc list-inside">
                                    <li>Renter will be marked qualified.</li>
                                    <li>Listing will be locked from new inquiries.</li>
                                    <li>Notes below are saved with the handoff.</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Renter notes (durable across inquiries) -->
                        <div class="mt-5">
                            <label for="handoff-renter-notes" class="block text-sm font-medium text-gray-900 dark:text-white">
                                About the renter (durable)
                            </label>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                Job, family situation, move-in window, accessibility needs, etc. Pre-filled from prior handoffs.
                            </p>
                            <textarea
                                id="handoff-renter-notes"
                                ref="handoffRenterNotesRef"
                                v-model="handoffForm.renter_notes"
                                rows="4"
                                placeholder="What should the next caller know about this renter?"
                                @blur="validateField('renter_notes')"
                                @focus="clearFieldError('renter_notes')"
                                class="mt-1.5 block w-full rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-white px-3 py-2 resize-y focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            ></textarea>
                            <div class="mt-1 flex items-center justify-between">
                                <p v-if="handoffErrors.renter_notes" class="text-xs text-red-600 dark:text-red-400">
                                    {{ handoffErrors.renter_notes }}
                                </p>
                                <p v-else class="text-xs text-gray-400 dark:text-gray-500">&nbsp;</p>
                                <p :class="counterClass('renter_notes')" class="text-xs font-mono">
                                    {{ counterText('renter_notes') }}
                                </p>
                            </div>
                        </div>

                        <!-- Inquiry notes (per-event) -->
                        <div class="mt-4">
                            <label for="handoff-notes" class="block text-sm font-medium text-gray-900 dark:text-white">
                                About this handoff
                            </label>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                What was agreed during the qualifying call? Special arrangements, viewing schedule, owner expectations, etc.
                            </p>
                            <textarea
                                id="handoff-notes"
                                ref="handoffNotesRef"
                                v-model="handoffForm.notes"
                                rows="4"
                                placeholder="What was agreed during the qualifying call?"
                                @blur="validateField('notes')"
                                @focus="clearFieldError('notes')"
                                class="mt-1.5 block w-full rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-white px-3 py-2 resize-y focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            ></textarea>
                            <div class="mt-1 flex items-center justify-between">
                                <p v-if="handoffErrors.notes" class="text-xs text-red-600 dark:text-red-400">
                                    {{ handoffErrors.notes }}
                                </p>
                                <p v-else class="text-xs text-gray-400 dark:text-gray-500">&nbsp;</p>
                                <p :class="counterClass('notes')" class="text-xs font-mono">
                                    {{ counterText('notes') }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-6 flex items-center justify-end gap-2">
                            <button
                                type="button"
                                @click="closeHandoffModal"
                                class="px-4 py-2 rounded-md border border-gray-300 dark:border-gray-700 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                @click="submitHandoff"
                                class="px-4 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold transition cursor-pointer"
                            >
                                Confirm Handoff
                            </button>
                        </div>
                    </div>
                </div>
            </Teleport>
        </div>
    </div>
</template>
