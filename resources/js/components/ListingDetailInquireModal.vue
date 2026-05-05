<script setup>
import { computed, inject, nextTick, ref, watch } from 'vue';

const props = defineProps({
    listingUuid: { type: String, required: true },
    csrfToken:   { type: String, required: true },
    oldInput:    { type: Object, default: null },
    errors:      { type: Object, default: null },
});

const showModal = inject('inquireModalOpen');

const CLIENT_VALIDATION_ENABLED = true;

const form = ref({
    name:  props.oldInput?.name ?? '',
    phone: props.oldInput?.phone ?? '',
});

const clientErrors = ref({});

function errorFor(field) {
    return props.errors?.[field]?.[0] || clientErrors.value[field] || null;
}

// Server errors not bound to a visible field (e.g. listing_uuid) surface
// as a banner above the form so renter still sees what went wrong.
const serverBannerError = computed(() => {
    return props.errors?.listing_uuid?.[0] || null;
});

const formEl = ref(null);

const VALIDATORS = {
    name: (v) => {
        if (!v || v.trim() === '') return 'Name is required.';
        if (v.length > 120) return 'Name must be 120 characters or fewer.';
        return null;
    },
    phone: (v) => {
        if (!v || v.trim() === '') return 'Phone is required.';
        const digits = (v.match(/\d/g) || []).join('');
        const ok =
            (digits.startsWith('63') && digits.length === 12) ||
            (digits.startsWith('09') && digits.length === 11) ||
            (digits.length === 10 && digits.startsWith('9'));
        if (!ok) return 'Invalid PH mobile number.';
        return null;
    },
};

function validateField(field) {
    if (!CLIENT_VALIDATION_ENABLED) return true;
    const fn = VALIDATORS[field];
    if (!fn) return true;
    const msg = fn(form.value[field]);
    if (msg) {
        clientErrors.value = { ...clientErrors.value, [field]: msg };
        return false;
    }
    const next = { ...clientErrors.value };
    delete next[field];
    clientErrors.value = next;
    return true;
}

function clearFieldError(field) {
    if (clientErrors.value[field]) {
        const next = { ...clientErrors.value };
        delete next[field];
        clientErrors.value = next;
    }
}

function validateAll() {
    if (!CLIENT_VALIDATION_ENABLED) return true;
    let ok = true;
    for (const field of Object.keys(VALIDATORS)) {
        if (!validateField(field)) ok = false;
    }
    return ok;
}

async function submit() {
    if (!validateAll()) {
        await nextTick();
        const body = document.querySelector('[data-inquire-modal-body]');
        if (body) body.scrollTop = 0;
        return;
    }
    formEl.value?.submit();
}

function close() {
    showModal.value = false;
}

watch(showModal, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-end md:items-start md:pt-24 justify-center"
            role="dialog"
            aria-modal="true"
        >
            <div class="absolute inset-0 bg-black/50" @click="close"></div>

            <div class="relative bg-white dark:bg-gray-900 rounded-t-2xl md:rounded-2xl w-full md:w-[28rem] max-h-[90vh] md:max-h-[80vh] overflow-hidden flex flex-col shadow-xl">
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-800 shrink-0">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                        Inquire about this listing
                    </h3>
                    <button
                        type="button"
                        @click="close"
                        class="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 cursor-pointer"
                        aria-label="Close"
                    >
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>

                <div data-inquire-modal-body class="flex-1 overflow-y-auto px-4 py-4">
                    <div
                        v-if="serverBannerError"
                        class="mb-4 p-3 rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-sm text-red-700 dark:text-red-300"
                    >
                        {{ serverBannerError }}
                    </div>

                    <form
                        ref="formEl"
                        method="POST"
                        action="/inquiries"
                        @submit.prevent="submit"
                        novalidate
                    >
                        <input type="hidden" name="_token" :value="csrfToken">
                        <input type="hidden" name="listing_uuid" :value="listingUuid">

                        <!-- Hidden submit button enables Enter-to-submit. The
                             visible button lives outside the form (modal footer)
                             so Enter wouldn't otherwise fire the form's submit
                             event. @submit.prevent on the form catches both. -->
                        <button type="submit" class="hidden" tabindex="-1" aria-hidden="true"></button>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                                <input
                                    type="text"
                                    name="name"
                                    v-model="form.name"
                                    @blur="validateField('name')"
                                    @focus="clearFieldError('name')"
                                    autocomplete="name"
                                    class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md px-3 py-2 text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-orange-500"
                                >
                                <p v-if="errorFor('name')" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ errorFor('name') }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone</label>
                                <input
                                    type="text"
                                    name="phone"
                                    v-model="form.phone"
                                    @blur="validateField('phone')"
                                    @focus="clearFieldError('phone')"
                                    inputmode="tel"
                                    autocomplete="tel"
                                    placeholder="0917 123 4567"
                                    class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md px-3 py-2 text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-orange-500"
                                >
                                <p v-if="errorFor('phone')" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ errorFor('phone') }}</p>
                            </div>

                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Our team will give you a call shortly.
                            </p>
                        </div>
                    </form>
                </div>

                <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-800 shrink-0 flex gap-2">
                    <button
                        type="button"
                        @click="close"
                        class="flex-1 px-4 py-2 rounded-md border border-gray-300 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="submit"
                        class="flex-1 px-4 py-2 rounded-md bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium cursor-pointer"
                    >
                        Submit inquiry
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
