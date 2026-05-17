<script setup>
import { computed } from 'vue';

const props = defineProps({
    listing: { type: Object, required: true },
});

const lockedInputClass = 'mt-1 w-full rounded-md border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 px-3.5 py-2.5 text-sm text-gray-900 dark:text-white cursor-not-allowed';

// Format any PH-mobile shape as 0917 711 0101 for display. Handles the
// raw 11-digit local form (09XXXXXXXXX), the 10-digit no-leading-zero
// form (9XXXXXXXXX), and the E.164 forms (+639XXXXXXXXX / 639XXXXXXXXX)
// that come back from the server after normalization.
const displayPhone = computed(() => {
    const phone = props.listing.listing_contact?.phone || null;
    if (!phone) return '';
    const digits = phone.replace(/\D/g, '');

    if (digits.length === 11 && digits.startsWith('09')) {
        return digits.slice(0, 4) + ' ' + digits.slice(4, 7) + ' ' + digits.slice(7);
    }
    if (digits.length === 12 && digits.startsWith('63')) {
        return '0' + digits.slice(2, 5) + ' ' + digits.slice(5, 8) + ' ' + digits.slice(8);
    }
    if (digits.length === 10 && digits.startsWith('9')) {
        return '0' + digits.slice(0, 3) + ' ' + digits.slice(3, 6) + ' ' + digits.slice(6);
    }
    return phone;
});
</script>

<template>
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm p-6 space-y-5">
        <div>
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Contact</h3>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Owner or broker contact for this listing. Set by the calls team — read-only on this surface.
            </p>
        </div>

        <div>
            <label for="contact-phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Phone</label>
            <input
                id="contact-phone"
                :value="displayPhone"
                readonly
                type="tel"
                :class="lockedInputClass"
            >
        </div>

        <div>
            <label for="contact-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
            <input
                id="contact-name"
                :value="listing.listing_contact?.name ?? ''"
                readonly
                type="text"
                :class="lockedInputClass"
            >
        </div>

        <div>
            <label for="contact-notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
            <textarea
                id="contact-notes"
                :value="listing.listing_contact?.notes ?? ''"
                readonly
                rows="3"
                :class="[lockedInputClass, 'resize-y']"
            ></textarea>
        </div>
    </div>
</template>
