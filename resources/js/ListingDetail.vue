<script setup>
import { provide, ref, onMounted, onBeforeUnmount } from 'vue';
import Navbar from './components/Navbar.vue';
import Footer from './components/Footer.vue';
import ListingDetailMobileHeader from './components/ListingDetailMobileHeader.vue';
import ListingDetailHero from './components/ListingDetailHero.vue';
import ListingDetailMeta from './components/ListingDetailMeta.vue';
import ListingDetailDescription from './components/ListingDetailDescription.vue';
import ListingDetailAmenities from './components/ListingDetailAmenities.vue';
import ListingDetailLocation from './components/ListingDetailLocation.vue';
import ListingDetailInquireModal from './components/ListingDetailInquireModal.vue';
import BottomNav from './components/BottomNav.vue';

const listing = ref(window.__INITIAL_LISTING__);

const inquire = window.__INITIAL_INQUIRE__ ?? { oldInput: null, errors: null, openInquireModal: false };
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const inquireModalOpen = ref(inquire.openInquireModal === true);
provide('inquireModalOpen', inquireModalOpen);

function openInquireModal() {
    inquireModalOpen.value = true;
}

function goBack() {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        window.location.href = '/';
    }
}

const touchStart = { x: 0, y: 0, time: 0, valid: false };

function startsInsideHorizontalScroller(target) {
    let el = target;
    while (el && el !== document.body) {
        if (el.scrollWidth > el.clientWidth + 1) {
            const style = getComputedStyle(el);
            if (style.overflowX === 'auto' || style.overflowX === 'scroll') {
                return true;
            }
        }
        el = el.parentElement;
    }
    return false;
}

function onTouchStart(e) {
    if (e.touches.length !== 1) {
        touchStart.valid = false;
        return;
    }
    if (startsInsideHorizontalScroller(e.target)) {
        touchStart.valid = false;
        return;
    }
    const t = e.touches[0];
    touchStart.x = t.clientX;
    touchStart.y = t.clientY;
    touchStart.time = Date.now();
    touchStart.valid = true;
}

function onTouchEnd(e) {
    if (!touchStart.valid) return;
    touchStart.valid = false;
    const t = e.changedTouches[0];
    const dx = t.clientX - touchStart.x;
    const dy = t.clientY - touchStart.y;
    const dt = Date.now() - touchStart.time;
    if (dx > 80 && Math.abs(dy) < 60 && dt < 600) {
        goBack();
    }
}

onMounted(() => {
    window.addEventListener('touchstart', onTouchStart, { passive: true });
    window.addEventListener('touchend', onTouchEnd, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('touchstart', onTouchStart);
    window.removeEventListener('touchend', onTouchEnd);
});
</script>

<template>
    <div class="min-h-screen bg-white text-gray-900 dark:bg-gray-950 dark:text-gray-100 pb-20 md:pb-0">
        <div class="hidden md:block">
            <Navbar />
        </div>
        <div class="md:hidden">
            <ListingDetailMobileHeader @back="goBack" />
        </div>

        <main class="max-w-7xl mx-auto px-4 md:px-6 lg:px-8 mt-4 md:mt-8">
            <a
                href="#"
                @click.prevent="goBack"
                class="hidden md:inline-flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 mb-4"
            >
                ← Back to listings
            </a>

            <div class="grid md:grid-cols-3 gap-6 lg:gap-10">
                <div class="md:col-span-2 space-y-8 min-w-0 overflow-hidden">
                    <div>
                        <ListingDetailHero :listing="listing" />
                        <ListingDetailMeta :listing="listing" :hide-cta="true" class="md:hidden mt-2" />
                    </div>
                    <ListingDetailDescription :listing="listing" />
                    <ListingDetailAmenities :listing="listing" />
                    <ListingDetailLocation :listing="listing" />
                </div>
                <aside class="hidden md:block md:col-span-1">
                    <ListingDetailMeta :listing="listing" class="md:sticky md:top-24" />
                </aside>
            </div>
        </main>

        <div
            class="md:hidden fixed left-0 right-0 z-[39] px-4 py-3 bg-white dark:bg-gray-900 border-t border-gray-100 dark:border-gray-800"
            style="bottom: 56px;"
        >
            <button
                type="button"
                @click="openInquireModal"
                class="w-full inline-flex items-center justify-center gap-2 bg-orange-500 hover:bg-orange-600 text-white font-medium rounded-xl px-5 py-3 text-sm transition cursor-pointer"
            >
                Inquire Now
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </button>
        </div>

        <div class="hidden md:block">
            <Footer />
        </div>
        <BottomNav />

        <ListingDetailInquireModal
            :listing-uuid="listing.uuid"
            :csrf-token="csrfToken"
            :old-input="inquire.oldInput"
            :errors="inquire.errors"
        />
    </div>
</template>
