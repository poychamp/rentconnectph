<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    listing: { type: Object, required: true },
});

const showShareModal = ref(false);
const copied = ref(false);
const linkInputRef = ref(null);
const shareUrl = ref('');
let copiedTimer = null;

function openSharePopup(url, w, h) {
    const left = window.screenX + Math.max(0, (window.outerWidth - w) / 2);
    const top = window.screenY + Math.max(0, (window.outerHeight - h) / 2);
    window.open(
        url,
        'share',
        `width=${w},height=${h},left=${left},top=${top},popup=yes,noopener,noreferrer`,
    );
}

function shareToFacebook() {
    const url = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(window.location.href);
    openSharePopup(url, 600, 600);
}

function shareToReddit() {
    const price = new Intl.NumberFormat('en-PH').format(props.listing.price_monthly);
    const title = `${props.listing.title} — ₱${price}/month in ${props.listing.barangay_label}`;
    const url = 'https://www.reddit.com/submit?url=' + encodeURIComponent(window.location.href) + '&title=' + encodeURIComponent(title);
    openSharePopup(url, 750, 700);
}

async function writeToClipboard(text) {
    try {
        if (globalThis.navigator?.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);
        } else {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
        }
        return true;
    } catch (_) {
        return false;
    }
}

function flashCopied() {
    copied.value = true;
    if (copiedTimer) clearTimeout(copiedTimer);
    copiedTimer = setTimeout(() => { copied.value = false; }, 2000);
}

async function openCopyModal() {
    shareUrl.value = window.location.href;
    showShareModal.value = true;
    copied.value = false;
    const ok = await writeToClipboard(shareUrl.value);
    if (ok) flashCopied();
    nextTick(() => linkInputRef.value?.select());
}

function closeCopyModal() {
    showShareModal.value = false;
}

async function copyAgain() {
    const ok = await writeToClipboard(shareUrl.value);
    if (ok) flashCopied();
    linkInputRef.value?.select();
}

function onKeydown(e) {
    if (e.key === 'Escape' && showShareModal.value) closeCopyModal();
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => {
    if (copiedTimer) clearTimeout(copiedTimer);
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div class="flex items-center gap-1">
        <button
            type="button"
            @click="shareToFacebook"
            aria-label="Share to Facebook"
            title="Share to Facebook"
            class="w-9 h-9 grid place-items-center rounded-full text-[#1877F2] hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
        >
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.879V14.89h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.563V12h2.773l-.443 2.89h-2.33v6.99C18.343 21.128 22 16.991 22 12Z"/></svg>
        </button>

        <button
            type="button"
            @click="shareToReddit"
            aria-label="Share to Reddit"
            title="Share to Reddit"
            class="w-9 h-9 grid place-items-center rounded-full text-[#FF4500] hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
        >
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M22 12.06c0-1.21-.98-2.19-2.19-2.19-.59 0-1.13.24-1.52.62-1.5-1.07-3.55-1.76-5.83-1.84l1-4.69 3.27.7c.04.83.71 1.49 1.55 1.49.86 0 1.55-.7 1.55-1.55 0-.86-.7-1.55-1.55-1.55-.61 0-1.13.36-1.39.87l-3.62-.77a.34.34 0 0 0-.4.26l-1.1 5.18c-2.31.07-4.39.76-5.91 1.84a2.18 2.18 0 0 0-1.52-.62c-1.21 0-2.19.98-2.19 2.19 0 .89.53 1.66 1.3 2-.04.21-.06.43-.06.66 0 3.32 3.86 6.01 8.61 6.01s8.61-2.69 8.61-6.01c0-.23-.02-.45-.06-.66.77-.34 1.3-1.11 1.3-2Zm-14 1.55c0-.86.7-1.55 1.55-1.55s1.55.7 1.55 1.55c0 .86-.7 1.55-1.55 1.55s-1.55-.7-1.55-1.55Zm8.41 4.07c-1 .99-2.79 1.07-3.41 1.07s-2.41-.08-3.41-1.07a.37.37 0 0 1 0-.52.37.37 0 0 1 .52 0c.63.63 1.97.85 2.89.85.92 0 2.27-.22 2.89-.85a.37.37 0 0 1 .52 0c.14.14.14.38 0 .52Zm-.18-2.51c-.86 0-1.55-.7-1.55-1.55 0-.86.7-1.55 1.55-1.55s1.55.7 1.55 1.55c0 .85-.7 1.55-1.55 1.55Z"/></svg>
        </button>

        <button
            type="button"
            @click="openCopyModal"
            aria-label="Copy link"
            title="Copy link"
            class="w-9 h-9 grid place-items-center rounded-full text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
        >
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
            </svg>
        </button>

        <Teleport to="body">
            <div
                v-if="showShareModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                @click.self="closeCopyModal"
            >
                <div class="absolute inset-0 bg-black/50" @click="closeCopyModal"></div>

                <div class="relative w-full max-w-md bg-white dark:bg-gray-900 rounded-2xl shadow-xl p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Share this listing</h2>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Copy the link and paste it anywhere — Messenger, email, group chats.
                            </p>
                        </div>
                        <button
                            type="button"
                            @click="closeCopyModal"
                            aria-label="Close"
                            class="w-8 h-8 grid place-items-center rounded-full text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer shrink-0"
                        >
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div class="mt-5 flex items-stretch gap-2">
                        <input
                            ref="linkInputRef"
                            type="text"
                            readonly
                            :value="shareUrl"
                            @focus="$event.target.select()"
                            class="flex-1 min-w-0 px-3 py-2.5 text-sm text-gray-900 dark:text-gray-100 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500"
                        >
                        <button
                            type="button"
                            @click="copyAgain"
                            class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium rounded-lg transition cursor-pointer shrink-0"
                            :class="copied
                                ? 'bg-emerald-500 text-white'
                                : 'bg-orange-500 hover:bg-orange-600 text-white'"
                        >
                            <svg v-if="!copied" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="9" y="9" width="13" height="13" rx="2"/>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                            </svg>
                            <svg v-else class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m5 13 4 4L19 7"/>
                            </svg>
                            {{ copied ? 'Copied' : 'Copy' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
