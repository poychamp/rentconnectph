<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';

const props = defineProps({
    lead:     { type: Object, required: true },
    errors:   { type: Object, default: () => ({}) },
    oldInput: { type: Object, default: () => ({}) },
});

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const createdAgo = computed(() => {
    if (!props.lead.created_at) return '';
    const then = new Date(props.lead.created_at);
    const diffMs = Date.now() - then.getTime();
    const diffMin = Math.floor(diffMs / 60000);
    if (diffMin < 1) return 'just now';
    if (diffMin < 60) return `${diffMin}m ago`;
    const diffHr = Math.floor(diffMin / 60);
    if (diffHr < 24) return `${diffHr}h ago`;
    const diffDay = Math.floor(diffHr / 24);
    if (diffDay < 7) return `${diffDay}d ago`;
    return then.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
});

const createdAbsolute = computed(() => {
    if (!props.lead.created_at) return '';
    return new Date(props.lead.created_at).toLocaleString('en-PH', {
        year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit',
    });
});

const statusBadgeClass = computed(() => {
    const colors = {
        pending:   'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300',
        sent:      'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300',
        finalized: 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300',
        lost:      'bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300',
    };
    return colors[props.lead.status] ?? 'bg-gray-100 dark:bg-gray-800/40 text-gray-700 dark:text-gray-300';
});

const renterBadge = computed(() => {
    if (props.lead.renter.is_qualified === true)  return { label: 'Returning',    color: 'emerald' };
    if (props.lead.renter.is_qualified === false) return { label: 'Disqualified', color: 'rose' };
    return null;
});

function toLocal(phone) {
    if (!phone || !phone.startsWith('+63')) return null;
    return '0' + phone.slice(3);
}

function fmtAbsolute(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString('en-PH', {
        year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit',
    });
}

const monthlyRentDisplay = computed(() => {
    if (props.lead.monthly_rent == null) return '—';
    return new Intl.NumberFormat('en-PH', {
        style: 'currency', currency: 'PHP', maximumFractionDigits: 0,
    }).format(props.lead.monthly_rent);
});

// ---------------------------------------------------------------------
// Mark as Sent — PUT /admin/leads/{uuid}/send (FRD-047).
// State A: lead.status === 'pending' → button + form + Teleport modal.
// Named bag: `send-{uuid}` per row.
// Notes overwrite (NOT append) — payload value replaces existing column;
// empty/missing payload normalizes to null and clears.
// Auto-open-on-error: when this row's named bag has errors, populate from
// oldInput.notes + open modal at mount.
// Defensive UX: when NOT a failed-submit target, pre-fill textarea with
// existing lead.notes so admin doesn't accidentally clear creation context.
// ---------------------------------------------------------------------

const CLIENT_VALIDATION_ENABLED_SEND = true;

const sendUrl = computed(() => `/admin/leads/${props.lead.uuid}/send`);

const sendBag        = `send-${props.lead.uuid}`;
const sendServerErrs = (props.errors && props.errors[sendBag]) ? props.errors[sendBag] : null;

const sendForm = ref({
    notes: sendServerErrs
        ? (props.oldInput?.notes ?? '')
        : (props.lead.notes ?? ''),
});

const sendErrors = ref({});

const sendNotesRef = ref(null);
const sendFormEl   = ref(null);
const showSendModal = ref(false);

const SEND_VALIDATORS = {
    notes: (val) => (val ?? '').length > 2000
        ? 'Lead notes must be 2000 characters or fewer.'
        : null,
};

function validateSendField(field) {
    if (!CLIENT_VALIDATION_ENABLED_SEND) return;
    const error = SEND_VALIDATORS[field]?.(sendForm.value[field]);
    if (error) sendErrors.value[field] = error;
    else delete sendErrors.value[field];
}

function clearSendFieldError(field) {
    delete sendErrors.value[field];
}

function validateAllSend() {
    if (!CLIENT_VALIDATION_ENABLED_SEND) return true;
    const next = {};
    for (const field of Object.keys(SEND_VALIDATORS)) {
        const error = SEND_VALIDATORS[field](sendForm.value[field]);
        if (error) next[field] = error;
    }
    sendErrors.value = next;
    return Object.keys(next).length === 0;
}

function counterClassSend(field) {
    return (sendForm.value[field] ?? '').length > 2000
        ? 'text-red-500 dark:text-red-400'
        : 'text-gray-500 dark:text-gray-400';
}

function counterTextSend(field) {
    return `${(sendForm.value[field] ?? '').length} / 2000`;
}

function openSendModal() {
    showSendModal.value = true;
    nextTick(() => sendNotesRef.value?.focus());
}

function closeSendModal() {
    showSendModal.value = false;
}

function submitSend() {
    if (!validateAllSend()) {
        nextTick(() => sendNotesRef.value?.focus());
        return;
    }
    sendFormEl.value?.submit();
}

// ---------------------------------------------------------------------
// Mark Lost — PUT /admin/leads/{uuid}/lose (FRD-048).
// State A: lead.status === 'pending' || 'sent' → button + form + modal.
// Named bag: `lose-{uuid}` per row.
// Notes overwrite (locked from PRD-047) — payload replaces existing
// column; empty/missing payload normalizes to null and clears.
// sent_at is PRESERVED on sent→lost flip (controller's update payload
// only touches status/lost_at/notes).
// Auto-open-on-error: when this row's named bag has errors, populate
// from oldInput.notes + open modal at mount.
// Defensive UX: when NOT a failed-submit target, pre-fill textarea with
// existing lead.notes so admin doesn't accidentally clear context.
// ---------------------------------------------------------------------

const CLIENT_VALIDATION_ENABLED_LOSE = true;

const loseUrl = computed(() => `/admin/leads/${props.lead.uuid}/lose`);

const loseBag        = `lose-${props.lead.uuid}`;
const loseServerErrs = (props.errors && props.errors[loseBag]) ? props.errors[loseBag] : null;

const loseForm = ref({
    notes: loseServerErrs
        ? (props.oldInput?.notes ?? '')
        : (props.lead.notes ?? ''),
});

const loseErrors = ref({});

const loseNotesRef = ref(null);
const loseFormEl   = ref(null);
const showLoseModal = ref(false);

const LOSE_VALIDATORS = {
    notes: (val) => (val ?? '').length > 2000
        ? 'Lead notes must be 2000 characters or fewer.'
        : null,
};

function validateLoseField(field) {
    if (!CLIENT_VALIDATION_ENABLED_LOSE) return;
    const error = LOSE_VALIDATORS[field]?.(loseForm.value[field]);
    if (error) loseErrors.value[field] = error;
    else delete loseErrors.value[field];
}

function clearLoseFieldError(field) {
    delete loseErrors.value[field];
}

function validateAllLose() {
    if (!CLIENT_VALIDATION_ENABLED_LOSE) return true;
    const next = {};
    for (const field of Object.keys(LOSE_VALIDATORS)) {
        const error = LOSE_VALIDATORS[field](loseForm.value[field]);
        if (error) next[field] = error;
    }
    loseErrors.value = next;
    return Object.keys(next).length === 0;
}

function counterClassLose(field) {
    return (loseForm.value[field] ?? '').length > 2000
        ? 'text-red-500 dark:text-red-400'
        : 'text-gray-500 dark:text-gray-400';
}

function counterTextLose(field) {
    return `${(loseForm.value[field] ?? '').length} / 2000`;
}

function openLoseModal() {
    showLoseModal.value = true;
    nextTick(() => loseNotesRef.value?.focus());
}

function closeLoseModal() {
    showLoseModal.value = false;
}

function submitLose() {
    if (!validateAllLose()) {
        nextTick(() => loseNotesRef.value?.focus());
        return;
    }
    loseFormEl.value?.submit();
}

// ---------------------------------------------------------------------
// Mark Finalized — PUT /admin/leads/{uuid}/finalize (FRD-049).
// State A: lead.status === 'sent' → button + form + Teleport modal.
// Named bag: `finalize-{uuid}` per row.
// Notes overwrite (locked from PRD-047) — payload replaces existing
// column; empty/missing payload normalizes to null and clears.
// sent_at is PRESERVED on sent→finalized flip (controller's update
// payload only touches status/finalized_at/notes). Pinned via test #13.
// Auto-open-on-error: when this row's named bag has errors, populate
// from oldInput.notes + open modal at mount.
// Defensive UX: when NOT a failed-submit target, pre-fill textarea with
// existing lead.notes (third consumer of pattern — codification candidate).
// ---------------------------------------------------------------------

const CLIENT_VALIDATION_ENABLED_FINALIZE = true;

const finalizeUrl = computed(() => `/admin/leads/${props.lead.uuid}/finalize`);

const finalizeBag        = `finalize-${props.lead.uuid}`;
const finalizeServerErrs = (props.errors && props.errors[finalizeBag]) ? props.errors[finalizeBag] : null;

const finalizeForm = ref({
    notes: finalizeServerErrs
        ? (props.oldInput?.notes ?? '')
        : (props.lead.notes ?? ''),
});

const finalizeErrors = ref({});

const finalizeNotesRef = ref(null);
const finalizeFormEl   = ref(null);
const showFinalizeModal = ref(false);

const FINALIZE_VALIDATORS = {
    notes: (val) => (val ?? '').length > 2000
        ? 'Lead notes must be 2000 characters or fewer.'
        : null,
};

function validateFinalizeField(field) {
    if (!CLIENT_VALIDATION_ENABLED_FINALIZE) return;
    const error = FINALIZE_VALIDATORS[field]?.(finalizeForm.value[field]);
    if (error) finalizeErrors.value[field] = error;
    else delete finalizeErrors.value[field];
}

function clearFinalizeFieldError(field) {
    delete finalizeErrors.value[field];
}

function validateAllFinalize() {
    if (!CLIENT_VALIDATION_ENABLED_FINALIZE) return true;
    const next = {};
    for (const field of Object.keys(FINALIZE_VALIDATORS)) {
        const error = FINALIZE_VALIDATORS[field](finalizeForm.value[field]);
        if (error) next[field] = error;
    }
    finalizeErrors.value = next;
    return Object.keys(next).length === 0;
}

function counterClassFinalize(field) {
    return (finalizeForm.value[field] ?? '').length > 2000
        ? 'text-red-500 dark:text-red-400'
        : 'text-gray-500 dark:text-gray-400';
}

function counterTextFinalize(field) {
    return `${(finalizeForm.value[field] ?? '').length} / 2000`;
}

function openFinalizeModal() {
    showFinalizeModal.value = true;
    nextTick(() => finalizeNotesRef.value?.focus());
}

function closeFinalizeModal() {
    showFinalizeModal.value = false;
}

function submitFinalize() {
    if (!validateAllFinalize()) {
        nextTick(() => finalizeNotesRef.value?.focus());
        return;
    }
    finalizeFormEl.value?.submit();
}

// Preview Message — generates an editable broker template from the lead's
// data. Pure frontend; no backend POST. navigator.clipboard with
// document.execCommand fallback for insecure-context dev (rentconnectph.test)
// per CLAUDE.md "Browser secure-context restrictions".
const showPreviewModal = ref(false);
const previewText      = ref('');
const copyState        = ref('idle');  // idle | success | error

function buildTemplate() {
    const l = props.lead;
    const url = window.location.origin + l.listing_detail_url;
    const lines = ['New lead from RentConnectPH:', ''];

    lines.push('LISTING');
    lines.push(l.listing.title);
    if (l.monthly_rent != null) {
        lines.push(`₱${Number(l.monthly_rent).toLocaleString('en-PH')}/month`);
    }
    lines.push(url);
    lines.push('');

    lines.push('OWNER CONTACT (call first)');
    if (l.listing.contact_phone) {
        const localOwner = toLocal(l.listing.contact_phone);
        lines.push(`Phone: ${localOwner ?? l.listing.contact_phone}`);
    }
    if (l.listing.contact_type_label) {
        lines.push(`Role: ${l.listing.contact_type_label}`);
    }
    if (l.listing.verification_notes) {
        lines.push(`Verification: ${l.listing.verification_notes}`);
    }
    lines.push('');

    lines.push('RENTER');
    lines.push(`Name: ${l.renter.name}`);
    const localRenter = toLocal(l.renter.phone);
    lines.push(`Phone: ${localRenter ?? l.renter.phone}`);
    if (l.renter.notes) {
        lines.push(`Renter notes: ${l.renter.notes}`);
    }
    if (l.inquiry.notes) {
        lines.push(`Inquiry context: ${l.inquiry.notes}`);
    }

    if (l.notes) {
        lines.push('');
        lines.push('LEAD CONTEXT');
        lines.push(l.notes);
    }

    return lines.join('\n');
}

function openPreviewModal() {
    previewText.value = buildTemplate();
    copyState.value   = 'idle';
    showPreviewModal.value = true;
}

function closePreviewModal() {
    showPreviewModal.value = false;
}

async function copyMessage() {
    const text = previewText.value;

    if (navigator.clipboard?.writeText) {
        try {
            await navigator.clipboard.writeText(text);
            copyState.value = 'success';
            setTimeout(() => { if (copyState.value === 'success') copyState.value = 'idle'; }, 2000);
            return;
        } catch (e) {
            // fall through to execCommand fallback (insecure-context dev)
        }
    }

    try {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        const ok = document.execCommand('copy');
        document.body.removeChild(ta);
        if (ok) {
            copyState.value = 'success';
            setTimeout(() => { if (copyState.value === 'success') copyState.value = 'idle'; }, 2000);
        } else {
            copyState.value = 'error';
            setTimeout(() => { if (copyState.value === 'error') copyState.value = 'idle'; }, 3000);
        }
    } catch (e) {
        copyState.value = 'error';
        setTimeout(() => { if (copyState.value === 'error') copyState.value = 'idle'; }, 3000);
    }
}

// On mount: if this row was the target of a failed submit (state-guard or
// validation), populate the matching local error state from the named bag
// and auto-open the modal so the admin sees the error + their retained
// input. Send + Lose + Finalize flows handled here as sibling blocks — do
// NOT add another onMounted call.
onMounted(() => {
    if (sendServerErrs) {
        for (const [field, messages] of Object.entries(sendServerErrs)) {
            sendErrors.value[field] = Array.isArray(messages) ? messages[0] : messages;
        }
        showSendModal.value = true;
    }

    if (loseServerErrs) {
        for (const [field, messages] of Object.entries(loseServerErrs)) {
            loseErrors.value[field] = Array.isArray(messages) ? messages[0] : messages;
        }
        showLoseModal.value = true;
    }

    if (finalizeServerErrs) {
        for (const [field, messages] of Object.entries(finalizeServerErrs)) {
            finalizeErrors.value[field] = Array.isArray(messages) ? messages[0] : messages;
        }
        showFinalizeModal.value = true;
    }
});
</script>

<template>
    <article class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg p-5 hover:shadow-sm transition-shadow">
        <!-- Header: listing + status badge + created-ago -->
        <div class="flex items-start justify-between gap-4 mb-4">
            <div class="min-w-0 flex-1">
                <a
                    :href="lead.listing_detail_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-base font-semibold text-gray-900 dark:text-white hover:text-orange-600 dark:hover:text-orange-400 transition truncate inline-flex items-center gap-1.5"
                >
                    {{ lead.listing.title }}
                    <svg class="w-3.5 h-3.5 opacity-60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14 21 3" />
                    </svg>
                </a>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ lead.listing.barangay_label }}</p>
            </div>
            <div class="shrink-0 flex items-center gap-2">
                <span :class="statusBadgeClass" class="text-[11px] px-2 py-0.5 rounded-full font-semibold uppercase tracking-wider">
                    {{ lead.status_label }}
                </span>
                <span :title="createdAbsolute" class="text-xs text-gray-500 dark:text-gray-400">{{ createdAgo }}</span>
            </div>
        </div>

        <!-- 2-col body: renter card + owner-contact card -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4">
            <!-- Renter card (orange) -->
            <div class="rounded-md bg-orange-50 dark:bg-orange-950/20 border border-orange-200 dark:border-orange-900/40 p-3">
                <div class="flex items-center justify-between gap-2 mb-1">
                    <span class="text-[10px] font-semibold uppercase tracking-wider text-orange-700 dark:text-orange-400">Call this renter</span>
                    <span
                        v-if="renterBadge"
                        :class="renterBadge.color === 'emerald'
                            ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300'
                            : 'bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300'"
                        class="text-[10px] px-1.5 py-0.5 rounded-full font-semibold"
                    >
                        {{ renterBadge.label }}
                    </span>
                </div>
                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ lead.renter.name }}</div>
                <div class="font-mono text-xs text-gray-700 dark:text-gray-300">{{ toLocal(lead.renter.phone) ?? lead.renter.phone }}</div>
                <div v-if="toLocal(lead.renter.phone)" class="font-mono text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">{{ lead.renter.phone }}</div>
                <div v-if="lead.renter.notes" class="text-xs mt-2 pt-2 border-t border-orange-200 dark:border-orange-900/40">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-orange-700 dark:text-orange-400 mb-0.5">Renter notes</p>
                    <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ lead.renter.notes }}</p>
                </div>
                <div v-if="lead.inquiry.notes" class="text-xs mt-2 pt-2 border-t border-orange-200 dark:border-orange-900/40">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-orange-700 dark:text-orange-400 mb-0.5">Inquiry notes</p>
                    <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ lead.inquiry.notes }}</p>
                </div>
            </div>

            <!-- Owner-contact card (gray) -->
            <div class="rounded-md bg-gray-50 dark:bg-gray-800/40 border border-gray-200 dark:border-gray-700 p-3">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">Owner contact</span>
                <div class="font-mono text-sm text-gray-900 dark:text-white mt-1">{{ toLocal(lead.listing.contact_phone) ?? lead.listing.contact_phone ?? '—' }}</div>
                <div v-if="lead.listing.contact_type_label" class="text-xs text-gray-600 dark:text-gray-300 mt-0.5">{{ lead.listing.contact_type_label }}</div>
                <p v-if="lead.listing.verification_notes" class="text-xs text-gray-700 dark:text-gray-300 mt-2 pt-2 border-t border-gray-200 dark:border-gray-700 whitespace-pre-wrap">
                    {{ lead.listing.verification_notes }}
                </p>
            </div>
        </div>

        <!-- Lead-specific block: monthly rent + lead notes + inquiry notes + verification notes -->
        <div class="rounded-md border border-gray-200 dark:border-gray-700 p-3 mb-4">
            <div class="flex items-center justify-between gap-2 mb-2">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">Lead context</span>
                <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ monthlyRentDisplay }}<span class="text-xs font-normal text-gray-500 dark:text-gray-400 ml-1">/ month</span></span>
            </div>
            <div v-if="lead.notes" class="text-xs">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-0.5">Lead notes</p>
                <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ lead.notes }}</p>
            </div>
            <div v-if="lead.inquiry.notes" class="text-xs mt-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-0.5">Inquiry notes</p>
                <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ lead.inquiry.notes }}</p>
            </div>
            <div v-if="lead.listing.verification_notes" class="text-xs mt-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-0.5">Verification notes</p>
                <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ lead.listing.verification_notes }}</p>
            </div>
        </div>

        <!-- Footer: created-by + state-companion timestamps + action buttons -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 pt-3 border-t border-gray-200 dark:border-gray-700">
            <div class="text-xs text-gray-500 dark:text-gray-400 space-y-0.5">
                <p>
                    Marked by
                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ lead.created_by_name ?? 'Deleted admin' }}</span>
                    on {{ fmtAbsolute(lead.created_at) }}
                </p>
                <p v-if="lead.sent_at">Sent on <span class="font-medium text-gray-700 dark:text-gray-300">{{ fmtAbsolute(lead.sent_at) }}</span></p>
                <p v-if="lead.finalized_at">Finalized on <span class="font-medium text-gray-700 dark:text-gray-300">{{ fmtAbsolute(lead.finalized_at) }}</span></p>
                <p v-if="lead.lost_at">Lost on <span class="font-medium text-gray-700 dark:text-gray-300">{{ fmtAbsolute(lead.lost_at) }}</span></p>
            </div>
            <div class="flex items-center gap-2 shrink-0 flex-wrap">
                <button
                    type="button"
                    @click="openPreviewModal"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 transition cursor-pointer"
                >
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z M14 2v6h6 M16 13H8 M16 17H8 M10 9H8" />
                    </svg>
                    Preview Message
                </button>
                <button
                    v-if="lead.status === 'pending'"
                    type="button"
                    @click="openSendModal"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md border border-blue-200 dark:border-blue-900/50 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition cursor-pointer"
                >
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 2 11 13 M22 2l-7 20-4-9-9-4 20-7z" />
                    </svg>
                    Mark as Sent
                </button>
                <button
                    v-if="lead.status === 'sent'"
                    type="button"
                    @click="openFinalizeModal"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md border border-emerald-200 dark:border-emerald-900/50 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition cursor-pointer"
                >
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                        <polyline points="22 4 12 14.01 9 11.01" />
                    </svg>
                    Mark Finalized
                </button>
                <button
                    v-if="lead.status === 'pending' || lead.status === 'sent'"
                    type="button"
                    @click="openLoseModal"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md border border-rose-200 dark:border-rose-900/50 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition cursor-pointer"
                >
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10" />
                        <path d="M15 9l-6 6 M9 9l6 6" />
                    </svg>
                    Mark Lost
                </button>
            </div>

            <!-- Hidden form for Mark-as-Sent (programmatic submit via submitSend) -->
            <form
                v-if="lead.status === 'pending'"
                ref="sendFormEl"
                :action="sendUrl"
                method="POST"
                class="hidden"
            >
                <input type="hidden" name="_token" :value="csrfToken">
                <input type="hidden" name="_method" value="PUT">
                <input type="hidden" name="notes" :value="sendForm.notes">
            </form>

            <!-- Hidden form for Mark Finalized (programmatic submit via submitFinalize) -->
            <form
                v-if="lead.status === 'sent'"
                ref="finalizeFormEl"
                :action="finalizeUrl"
                method="POST"
                class="hidden"
            >
                <input type="hidden" name="_token" :value="csrfToken">
                <input type="hidden" name="_method" value="PUT">
                <input type="hidden" name="notes" :value="finalizeForm.notes">
            </form>

            <!-- Hidden form for Mark Lost (programmatic submit via submitLose) -->
            <form
                v-if="lead.status === 'pending' || lead.status === 'sent'"
                ref="loseFormEl"
                :action="loseUrl"
                method="POST"
                class="hidden"
            >
                <input type="hidden" name="_token" :value="csrfToken">
                <input type="hidden" name="_method" value="PUT">
                <input type="hidden" name="notes" :value="loseForm.notes">
            </form>

            <Teleport to="body">
                <!-- Preview Message modal -->
                <div
                    v-if="showPreviewModal"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4"
                    role="dialog"
                    aria-modal="true"
                >
                    <div class="absolute inset-0 bg-black/50" @click="closePreviewModal"></div>
                    <div class="relative w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-xl p-6">
                        <div class="flex items-start justify-between gap-4 mb-3">
                            <div class="min-w-0">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Message to broker</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    Edit before copying. Draft only — send via your usual channel (SMS, FB Messenger, etc.).
                                </p>
                            </div>
                            <button
                                type="button"
                                @click="closePreviewModal"
                                class="shrink-0 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition cursor-pointer"
                                aria-label="Close"
                            >
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M18 6 6 18 M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <textarea
                            v-model="previewText"
                            rows="14"
                            class="w-full font-mono text-xs rounded-md border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 p-3 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent resize-y"
                        ></textarea>
                        <div class="mt-4 flex items-center justify-between gap-2 flex-wrap">
                            <p
                                v-if="copyState === 'success'"
                                class="text-xs text-emerald-600 dark:text-emerald-400 font-medium"
                            >
                                Copied to clipboard.
                            </p>
                            <p
                                v-else-if="copyState === 'error'"
                                class="text-xs text-rose-600 dark:text-rose-400 font-medium"
                            >
                                Couldn't copy automatically. Select the text and use Ctrl/⌘+C.
                            </p>
                            <span v-else class="text-xs text-transparent select-none">.</span>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="closePreviewModal"
                                    class="px-3 py-1.5 rounded-md border border-gray-300 dark:border-gray-700 text-xs font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
                                >
                                    Close
                                </button>
                                <button
                                    type="button"
                                    @click="copyMessage"
                                    class="px-3 py-1.5 rounded-md bg-orange-500 hover:bg-orange-600 text-white text-xs font-semibold transition cursor-pointer"
                                >
                                    Copy
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mark-as-Sent modal (FRD-047) -->
                <div
                    v-if="showSendModal"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4"
                    role="dialog"
                    aria-modal="true"
                >
                    <div class="absolute inset-0 bg-black/50" @click="closeSendModal"></div>
                    <div class="relative w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-xl p-6">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0 inline-flex items-center justify-center w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 2 11 13 M22 2l-7 20-4-9-9-4 20-7z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                    Mark as Sent?
                                </h3>
                                <ul class="mt-2 text-sm text-gray-500 dark:text-gray-400 space-y-1 list-disc list-inside">
                                    <li>Sets the <strong>Sent on …</strong> timestamp on this lead.</li>
                                    <li>Lead transitions to <strong>sent</strong> and can no longer be re-sent.</li>
                                </ul>
                            </div>
                        </div>

                        <div
                            v-if="sendErrors._state"
                            class="mt-4 rounded-md border border-rose-200 dark:border-rose-900/40 bg-rose-50 dark:bg-rose-950/20 px-3 py-2 text-xs text-rose-700 dark:text-rose-300"
                        >
                            {{ sendErrors._state }}
                        </div>

                        <div class="mt-5">
                            <label for="send-notes" class="block text-sm font-medium text-gray-900 dark:text-white">
                                Lead notes (optional)
                            </label>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                Replaces existing lead notes; clear to remove.
                            </p>
                            <textarea
                                id="send-notes"
                                ref="sendNotesRef"
                                v-model="sendForm.notes"
                                rows="4"
                                placeholder='Where + when sent (e.g. "FB Messenger 2pm — Marco confirmed receipt")'
                                @blur="validateSendField('notes')"
                                @focus="clearSendFieldError('notes')"
                                class="mt-1.5 block w-full rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-white px-3 py-2 resize-y focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            ></textarea>
                            <div class="mt-1 flex items-center justify-between">
                                <p v-if="sendErrors.notes" class="text-xs text-red-600 dark:text-red-400">
                                    {{ sendErrors.notes }}
                                </p>
                                <p v-else class="text-xs text-gray-400 dark:text-gray-500">&nbsp;</p>
                                <p :class="counterClassSend('notes')" class="text-xs font-mono">
                                    {{ counterTextSend('notes') }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-6 flex items-center justify-end gap-2">
                            <button
                                type="button"
                                @click="closeSendModal"
                                class="px-4 py-2 rounded-md border border-gray-300 dark:border-gray-700 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                @click="submitSend"
                                class="px-4 py-2 rounded-md bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold transition cursor-pointer"
                            >
                                Confirm Sent
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Mark Finalized modal (FRD-049) -->
                <div
                    v-if="showFinalizeModal"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4"
                    role="dialog"
                    aria-modal="true"
                >
                    <div class="absolute inset-0 bg-black/50" @click="closeFinalizeModal"></div>
                    <div class="relative w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-xl p-6">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0 inline-flex items-center justify-center w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                                    <polyline points="22 4 12 14.01 9 11.01" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                    Mark as Finalized?
                                </h3>
                                <ul class="mt-2 text-sm text-gray-500 dark:text-gray-400 space-y-1 list-disc list-inside">
                                    <li>Sets the <strong>Finalized on …</strong> timestamp on this lead.</li>
                                    <li>Lead transitions to <strong>finalized</strong> (terminal — deal closed, won).</li>
                                </ul>
                            </div>
                        </div>

                        <div
                            v-if="finalizeErrors._state"
                            class="mt-4 rounded-md border border-rose-200 dark:border-rose-900/40 bg-rose-50 dark:bg-rose-950/20 px-3 py-2 text-xs text-rose-700 dark:text-rose-300"
                        >
                            {{ finalizeErrors._state }}
                        </div>

                        <div class="mt-5">
                            <label for="finalize-notes" class="block text-sm font-medium text-gray-900 dark:text-white">
                                Lead notes (optional)
                            </label>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                Replaces existing lead notes; clear to remove.
                            </p>
                            <textarea
                                id="finalize-notes"
                                ref="finalizeNotesRef"
                                v-model="finalizeForm.notes"
                                rows="4"
                                placeholder='Closure context (e.g. "Lease signed Tue 4pm — final rent ₱22,000, 12-month term")'
                                @blur="validateFinalizeField('notes')"
                                @focus="clearFinalizeFieldError('notes')"
                                class="mt-1.5 block w-full rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-white px-3 py-2 resize-y focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            ></textarea>
                            <div class="mt-1 flex items-center justify-between">
                                <p v-if="finalizeErrors.notes" class="text-xs text-red-600 dark:text-red-400">
                                    {{ finalizeErrors.notes }}
                                </p>
                                <p v-else class="text-xs text-gray-400 dark:text-gray-500">&nbsp;</p>
                                <p :class="counterClassFinalize('notes')" class="text-xs font-mono">
                                    {{ counterTextFinalize('notes') }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-6 flex items-center justify-end gap-2">
                            <button
                                type="button"
                                @click="closeFinalizeModal"
                                class="px-4 py-2 rounded-md border border-gray-300 dark:border-gray-700 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                @click="submitFinalize"
                                class="px-4 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold transition cursor-pointer"
                            >
                                Confirm Finalized
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Mark Lost modal (FRD-048) -->
                <div
                    v-if="showLoseModal"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4"
                    role="dialog"
                    aria-modal="true"
                >
                    <div class="absolute inset-0 bg-black/50" @click="closeLoseModal"></div>
                    <div class="relative w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-xl p-6">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0 inline-flex items-center justify-center w-10 h-10 rounded-full bg-rose-100 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10" />
                                    <path d="M15 9l-6 6 M9 9l6 6" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                    Mark as Lost?
                                </h3>
                                <ul class="mt-2 text-sm text-gray-500 dark:text-gray-400 space-y-1 list-disc list-inside">
                                    <li>Sets the <strong>Lost on …</strong> timestamp on this lead.</li>
                                    <li>Lead transitions to <strong>lost</strong> (terminal — cannot be re-opened).</li>
                                </ul>
                            </div>
                        </div>

                        <div
                            v-if="loseErrors._state"
                            class="mt-4 rounded-md border border-rose-200 dark:border-rose-900/40 bg-rose-50 dark:bg-rose-950/20 px-3 py-2 text-xs text-rose-700 dark:text-rose-300"
                        >
                            {{ loseErrors._state }}
                        </div>

                        <div class="mt-5">
                            <label for="lose-notes" class="block text-sm font-medium text-gray-900 dark:text-white">
                                Lead notes (optional)
                            </label>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                Replaces existing lead notes; clear to remove.
                            </p>
                            <textarea
                                id="lose-notes"
                                ref="loseNotesRef"
                                v-model="loseForm.notes"
                                rows="4"
                                placeholder='Why this deal died (e.g. "renter ghosted Marco after 3 attempts", "owner withdrew property")'
                                @blur="validateLoseField('notes')"
                                @focus="clearLoseFieldError('notes')"
                                class="mt-1.5 block w-full rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-white px-3 py-2 resize-y focus:outline-none focus:ring-2 focus:ring-rose-500 focus:border-rose-500"
                            ></textarea>
                            <div class="mt-1 flex items-center justify-between">
                                <p v-if="loseErrors.notes" class="text-xs text-red-600 dark:text-red-400">
                                    {{ loseErrors.notes }}
                                </p>
                                <p v-else class="text-xs text-gray-400 dark:text-gray-500">&nbsp;</p>
                                <p :class="counterClassLose('notes')" class="text-xs font-mono">
                                    {{ counterTextLose('notes') }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-6 flex items-center justify-end gap-2">
                            <button
                                type="button"
                                @click="closeLoseModal"
                                class="px-4 py-2 rounded-md border border-gray-300 dark:border-gray-700 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                @click="submitLose"
                                class="px-4 py-2 rounded-md bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold transition cursor-pointer"
                            >
                                Confirm Lost
                            </button>
                        </div>
                    </div>
                </div>
            </Teleport>
        </div>
    </article>
</template>
