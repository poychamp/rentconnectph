<script setup>
import { ref, inject, computed, onMounted, onUnmounted } from 'vue';

const form = inject('addListingForm');

const initial               = window.__INITIAL_EDIT_LISTING__ ?? {};
const initialOldInput       = initial.oldInput ?? {};
const initialDeactivateErrs = initial.deactivateErrors ?? null;

// If the page reloaded after a failed deactivate (server-side validation), the
// modal auto-reopens with the submitted values + the errors visible. Detect this
// by the presence of any deactivate-bag errors at setup time.
const hadDeactivateError = !!initialDeactivateErrs && Object.keys(initialDeactivateErrs).length > 0;

const open       = ref(hadDeactivateError);
const reason     = ref(hadDeactivateError ? (initialOldInput.reason ?? '') : '');
const notes      = ref(hadDeactivateError ? (initialOldInput.notes  ?? '') : '');
const errors     = ref(initialDeactivateErrs ?? {});
const submitting = ref(false);

const reasons     = initial.deactivationReasons ?? [];
const listingUuid = initial.listingUuid ?? '';
const csrfToken   = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const canConfirm = computed(() => !submitting.value);
const visible    = computed(() => form?.from === 'verified');

function openModal() {
    reason.value = '';
    notes.value  = '';
    errors.value = {};
    open.value   = true;
}

function closeModal() {
    if (submitting.value) return;
    open.value = false;
}

// Client-side validators mirror the server messages exactly so the UX stays
// consistent whether the failure is caught locally or after the round-trip.
// Server is still the source of truth; this just saves a round-trip.
function validateLocal() {
    const next = {};
    if (!reason.value) {
        next.reason = ['Reason is required.'];
    }
    if (notes.value && notes.value.length > 1000) {
        next.notes = ['Notes are too long (max 1000 characters).'];
    }
    return next;
}

function clearError(field) {
    if (!errors.value[field]) return;
    const next = { ...errors.value };
    delete next[field];
    errors.value = next;
}

function validateField(field) {
    const all = validateLocal();
    if (all[field]) {
        errors.value = { ...errors.value, [field]: all[field] };
    } else {
        clearError(field);
    }
}

function confirm() {
    if (!canConfirm.value) return;

    const localErrors = validateLocal();
    if (Object.keys(localErrors).length > 0) {
        errors.value = localErrors;
        return;
    }

    submitting.value = true;

    const formEl = document.createElement('form');
    formEl.method = 'POST';
    formEl.action = `/admin/listings/${listingUuid}/deactivate`;
    formEl.style.display = 'none';

    const append = (name, value) => {
        const i = document.createElement('input');
        i.type  = 'hidden';
        i.name  = name;
        i.value = value;
        formEl.appendChild(i);
    };

    append('_token',  csrfToken);
    // Browsers can't natively submit PUT — Laravel reads _method=PUT via MethodOverride.
    append('_method', 'PUT');
    append('reason',  reason.value);
    if (notes.value) append('notes', notes.value);

    document.body.appendChild(formEl);
    formEl.submit();
}

function onKeydown(e) {
    if (e.key === 'Escape' && open.value) closeModal();
}

onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
    <div
        v-if="visible"
        class="mt-8 rounded-lg border border-red-200 dark:border-red-900/60 bg-red-50/40 dark:bg-red-950/20"
    >
        <div class="px-6 py-3 border-b border-red-200 dark:border-red-900/60 flex items-center gap-2">
            <svg class="w-5 h-5 text-red-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            <h2 class="text-base font-semibold text-red-700 dark:text-red-300">Danger Zone</h2>
        </div>

        <div class="px-6 py-5 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="flex-1">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Deactivate this listing</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Pulls this listing from public discovery and writes an audit-log entry.
                </p>
            </div>
            <button
                type="button"
                @click="openModal"
                class="shrink-0 inline-flex items-center gap-1.5 px-4 py-2 rounded-md text-sm font-medium border border-red-300 dark:border-red-800/80 text-red-700 dark:text-red-300 bg-white dark:bg-gray-900 hover:bg-red-50 dark:hover:bg-red-950/40 transition cursor-pointer"
            >
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
                </svg>
                Deactivate
            </button>
        </div>
    </div>

    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="deactivate-modal-title"
        >
            <div
                class="absolute inset-0 bg-black/50 backdrop-blur-sm"
                @click="closeModal"
            ></div>

            <div class="relative w-full max-w-lg rounded-lg bg-white dark:bg-gray-900 shadow-xl border border-gray-200 dark:border-gray-800">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
                    <h3 id="deactivate-modal-title" class="text-base font-semibold text-gray-900 dark:text-gray-100">
                        Deactivate listing?
                    </h3>
                    <button
                        type="button"
                        @click="closeModal"
                        :disabled="submitting"
                        aria-label="Close"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 disabled:opacity-50 cursor-pointer"
                    >
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        This pulls
                        <span class="font-medium text-gray-900 dark:text-gray-100">"{{ form.title || 'this listing' }}"</span>
                        from public discovery and writes an audit log entry.
                    </p>

                    <div
                        v-if="errors.listing"
                        class="mb-4 rounded-md border border-red-200 dark:border-red-900/60 bg-red-50 dark:bg-red-950/30 px-3 py-2 text-sm text-red-800 dark:text-red-200"
                        role="alert"
                    >
                        {{ errors.listing[0] }}
                    </div>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Reason <span class="text-red-500">*</span>
                    </label>
                    <select
                        v-model="reason"
                        @change="validateField('reason')"
                        @focus="clearError('reason')"
                        :class="[
                            'w-full rounded-md border bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-100 px-3 py-2 focus:outline-none focus:ring-2',
                            errors.reason
                                ? 'border-red-500 dark:border-red-700 focus:ring-red-500 focus:border-red-500'
                                : 'border-gray-300 dark:border-gray-700 focus:ring-orange-500 focus:border-orange-500',
                        ]"
                    >
                        <option value="" disabled>Pick a reason…</option>
                        <option
                            v-for="r in reasons"
                            :key="r.value"
                            :value="r.value"
                        >{{ r.label }}</option>
                    </select>
                    <p
                        v-if="errors.reason"
                        class="mt-1 text-xs text-red-600 dark:text-red-400"
                    >{{ errors.reason[0] }}</p>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 mt-4">
                        Notes <span class="text-gray-400 text-xs font-normal">(optional)</span>
                    </label>
                    <textarea
                        v-model="notes"
                        @blur="validateField('notes')"
                        @focus="clearError('notes')"
                        rows="3"
                        placeholder="Optional context (e.g. tenant name, expected vacancy)."
                        :class="[
                            'w-full rounded-md border bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-100 px-3 py-2 focus:outline-none focus:ring-2',
                            errors.notes
                                ? 'border-red-500 dark:border-red-700 focus:ring-red-500 focus:border-red-500'
                                : 'border-gray-300 dark:border-gray-700 focus:ring-orange-500 focus:border-orange-500',
                        ]"
                    ></textarea>
                    <p
                        v-if="errors.notes"
                        class="mt-1 text-xs text-red-600 dark:text-red-400"
                    >{{ errors.notes[0] }}</p>
                    <p
                        :class="[
                            'mt-1 text-xs',
                            notes.length > 1000 ? 'text-red-600 dark:text-red-400 font-medium' : 'text-gray-400',
                        ]"
                    >{{ notes.length }} / 1000</p>
                </div>

                <div class="px-6 py-3 border-t border-gray-200 dark:border-gray-800 flex items-center justify-end gap-2">
                    <button
                        type="button"
                        @click="closeModal"
                        :disabled="submitting"
                        class="px-4 py-2 rounded-md text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer disabled:opacity-60"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="confirm"
                        :disabled="!canConfirm"
                        class="px-4 py-2 rounded-md bg-red-600 hover:bg-red-700 text-white text-sm font-medium transition cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed inline-flex items-center gap-2"
                    >
                        <svg v-if="submitting" class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-opacity="0.25"/>
                            <path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                        </svg>
                        {{ submitting ? 'Deactivating…' : 'Deactivate' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
