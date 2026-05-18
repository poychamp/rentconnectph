<script setup>
import { ref } from 'vue';
import Navbar from './components/Navbar.vue';
import Footer from './components/Footer.vue';
import BottomNav from './components/BottomNav.vue';
import ListingDetailMobileHeader from './components/ListingDetailMobileHeader.vue';

const initial = window.__INITIAL_INQUIRY_SUCCESS__ ?? { listing: null };
const listing = initial.listing;

const copied = ref(false);

function goBack() {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        window.location.href = '/';
    }
}

function toLocalDigits(phone) {
    if (!phone) return '';
    const digits = phone.replace(/\D/g, '');
    if (digits.startsWith('63') && digits.length === 12) {
        return `0${digits.slice(2)}`;
    }
    if (digits.startsWith('09') && digits.length === 11) {
        return digits;
    }
    if (digits.startsWith('9') && digits.length === 10) {
        return `0${digits}`;
    }
    return phone;
}

function formatPhoneDisplay(phone) {
    const local = toLocalDigits(phone);
    if (local.length === 11) {
        return `${local.slice(0, 4)} ${local.slice(4, 7)} ${local.slice(7)}`;
    }
    return phone;
}

async function copyPhone() {
    const phone = listing?.listing_contact?.phone;
    if (!phone) return;

    const localPhone = toLocalDigits(phone);

    let ok = false;
    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(localPhone);
            ok = true;
        } catch (e) { /* fall through to legacy path */ }
    }
    if (!ok) {
        const textarea = document.createElement('textarea');
        textarea.value = localPhone;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            ok = document.execCommand('copy');
        } catch (e) { /* swallow */ }
        document.body.removeChild(textarea);
    }

    if (ok) {
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 2000);
    }
}
</script>

<template>
    <div class="min-h-screen bg-white text-gray-900 dark:bg-gray-950 dark:text-gray-100 pb-20 md:pb-0 flex flex-col">
        <div class="hidden md:block">
            <Navbar />
        </div>
        <div class="md:hidden">
            <ListingDetailMobileHeader @back="goBack" />
        </div>

        <main class="flex-1 flex items-start justify-center px-4 py-8 md:py-16">
            <div class="max-w-md w-full">
                <div class="text-center mb-8">
                    <div class="mx-auto mb-5 w-16 h-16 rounded-full bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center">
                        <svg class="w-8 h-8 text-orange-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white mb-2">
                        Thanks — we got your inquiry
                    </h1>
                    <p v-if="listing?.title" class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                        Here's how to reach out about <span class="font-semibold text-gray-900 dark:text-white">{{ listing.title }}</span>.
                    </p>
                    <p v-else class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                        We got your inquiry.
                    </p>
                </div>

                <div
                    v-if="listing?.listing_contact?.phone"
                    class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-sm overflow-hidden"
                >
                    <div class="p-5 border-b border-gray-100 dark:border-gray-800 text-center">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                            Contact number<span v-if="listing.contact_type_label"> · {{ listing.contact_type_label }}</span>
                        </p>
                        <p class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white mb-4 break-all">
                            {{ formatPhoneDisplay(listing.listing_contact.phone) }}
                        </p>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                @click="copyPhone"
                                class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg border border-orange-500 text-orange-500 text-sm font-semibold transition-opacity duration-[80ms] ease-out active:opacity-50"
                            >
                                <svg v-if="!copied" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                                </svg>
                                <svg v-else class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                                {{ copied ? 'Copied' : 'Copy' }}
                            </button>
                            <a
                                :href="`tel:${listing.listing_contact.phone}`"
                                class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-orange-500 text-white text-sm font-semibold transition-opacity duration-[80ms] ease-out active:opacity-50"
                            >
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                </svg>
                                Call
                            </a>
                        </div>
                    </div>

                    <div v-if="listing.listing_contact.name" class="px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                            Ask for
                        </p>
                        <p class="text-base font-medium text-gray-900 dark:text-white">
                            {{ listing.listing_contact.name }}
                        </p>
                    </div>

                    <div v-if="listing.listing_contact.notes" class="px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                            Notes
                        </p>
                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-line">
                            {{ listing.listing_contact.notes }}
                        </p>
                    </div>

                    <div v-if="listing.barangay" class="px-5 py-4">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                            Barangay
                        </p>
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            {{ listing.barangay }}
                        </p>
                    </div>
                </div>

                <div class="mt-8 text-center">
                    <a
                        href="#"
                        @click.prevent="goBack"
                        class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition cursor-pointer"
                    >
                        ← Back to listing
                    </a>
                </div>
            </div>
        </main>

        <div class="hidden md:block">
            <Footer />
        </div>
        <BottomNav />
    </div>
</template>
