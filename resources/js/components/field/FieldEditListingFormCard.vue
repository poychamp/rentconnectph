<script setup>
import { computed, inject, ref } from 'vue';
import FieldEditListingMapPicker from './FieldEditListingMapPicker.vue';
import FieldEditListingPhotoUpload from './FieldEditListingPhotoUpload.vue';

const props = defineProps({
    listing:      { type: Object, required: true },
    listingTypes: { type: Array, required: true },
    barangays:    { type: Array, required: true },
    sourceSites:  { type: Array, required: true },
    contactTypes: { type: Array, required: true },
    amenities:    { type: Array, required: true },
});

const form = inject('addListingForm');
const errors = inject('addListingFormErrors', ref({}));
const { validateField, clearFieldError } = inject('addListingFormValidate', {
    validateField: () => true,
    clearFieldError: () => {},
});

const errorFor = (field) => errors.value?.[field]?.[0] ?? null;

const amenitiesError = computed(() => {
    const bag = errors.value ?? {};
    if (bag.amenities?.[0]) return bag.amenities[0];
    const rowKey = Object.keys(bag).find(k => k.startsWith('amenities.'));
    return rowKey ? bag[rowKey][0] : null;
});

const inputClassBase = 'mt-1 w-full rounded-md border bg-white dark:bg-gray-800 px-3.5 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:ring-1 outline-none transition';
const inputClassOk = 'border-gray-200 dark:border-gray-700 focus:border-orange-500 focus:ring-orange-500';
const inputClassError = 'border-red-400 dark:border-red-500 focus:border-red-500 focus:ring-red-500';

const inputClassFor = (field) => [inputClassBase, errorFor(field) ? inputClassError : inputClassOk];

const lockedClass = 'mt-1 w-full rounded-md border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 px-3.5 py-2.5 text-sm text-gray-900 dark:text-white cursor-not-allowed';
</script>

<template>
    <div class="space-y-5">
        <!-- LEAD: contact_phone (read-only) + source_site + source_url -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label for="contact-phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Contact phone</label>
                    <input
                        id="contact-phone"
                        :value="listing.contact_phone"
                        readonly
                        type="tel"
                        :class="lockedClass"
                    >
                </div>

                <div>
                    <label for="source-site" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Source site</label>
                    <select
                        id="source-site"
                        :value="listing.source_site"
                        disabled
                        :class="lockedClass"
                    >
                        <option value="">— None —</option>
                        <option v-for="s in sourceSites" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                </div>

                <div>
                    <label for="source-url" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Source URL</label>
                    <input
                        id="source-url"
                        :value="listing.source_url"
                        readonly
                        type="url"
                        :class="lockedClass"
                    >
                </div>
            </div>
        </div>

        <!-- CONTACT TYPE + DIRECTIONS + NOTES: full width, below contact details -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm p-6 space-y-5">
            <div>
                <label for="contact-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Contact type</label>
                <input
                    id="contact-type"
                    :value="listing.contact_type_label || ''"
                    readonly
                    type="text"
                    :class="lockedClass"
                >
            </div>

            <div>
                <label for="directions" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Directions <span class="text-red-500">*</span>
                </label>
                <textarea
                    id="directions"
                    v-model="form.directions"
                    rows="4"
                    @focus="clearFieldError('directions')"
                    @blur="validateField('directions')"
                    :class="[...inputClassFor('directions'), 'resize-y']"
                ></textarea>
                <p v-if="errorFor('directions')" class="mt-1 text-xs text-red-600 dark:text-red-400">
                    {{ errorFor('directions') }}
                </p>
            </div>

            <div>
                <label for="verification-notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Notes for field officer
                </label>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 mb-1">
                    Pre-visit context from the calls team.
                </p>
                <textarea
                    id="verification-notes"
                    :value="listing.verification_notes"
                    readonly
                    rows="4"
                    :class="[lockedClass, 'resize-y']"
                ></textarea>
            </div>
        </div>

        <!-- TOP ROW: two 50/50 cards side by side -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <!-- LEFT: title, description, photos -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm p-6 space-y-5">
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Title <span class="text-red-500">*</span>
                    </label>
                    <input
                        id="title"
                        v-model="form.title"
                        type="text"
                        @focus="clearFieldError('title')"
                        @blur="validateField('title')"
                        :class="inputClassFor('title')"
                    >
                    <p v-if="errorFor('title')" class="mt-1 text-xs text-red-600 dark:text-red-400">
                        {{ errorFor('title') }}
                    </p>
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="4"
                        @focus="clearFieldError('description')"
                        @blur="validateField('description')"
                        :class="[...inputClassFor('description'), 'resize-y']"
                    ></textarea>
                    <p v-if="errorFor('description')" class="mt-1 text-xs text-red-600 dark:text-red-400">
                        {{ errorFor('description') }}
                    </p>
                </div>

                <FieldEditListingPhotoUpload />
            </div>

            <!-- RIGHT: type, price, barangay, lat/lng + map -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm p-6 space-y-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="listing-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Listing type</label>
                        <select
                            id="listing-type"
                            v-model="form.listing_type"
                            @focus="clearFieldError('listing_type')"
                            @change="clearFieldError('listing_type')"
                            @blur="validateField('listing_type')"
                            :class="inputClassFor('listing_type')"
                        >
                            <option value="" disabled>Select type...</option>
                            <option v-for="t in listingTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                        <p v-if="errorFor('listing_type')" class="mt-1 text-xs text-red-600 dark:text-red-400">
                            {{ errorFor('listing_type') }}
                        </p>
                    </div>

                    <div>
                        <label for="price-monthly" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Monthly rent</label>
                        <div class="mt-1 relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-500 dark:text-gray-400 pointer-events-none">₱</span>
                            <input
                                id="price-monthly"
                                v-model.number="form.price_monthly"
                                type="number"
                                min="0"
                                placeholder="0"
                                @focus="clearFieldError('price_monthly')"
                                @blur="validateField('price_monthly')"
                                :class="[
                                    'w-full rounded-md border bg-white dark:bg-gray-800 pl-7 pr-16 py-2.5 text-sm text-right text-gray-900 dark:text-white placeholder-gray-400 focus:ring-1 outline-none transition',
                                    errorFor('price_monthly') ? inputClassError : inputClassOk,
                                ]"
                            >
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400 dark:text-gray-500 pointer-events-none">/ month</span>
                        </div>
                        <p v-if="errorFor('price_monthly')" class="mt-1 text-xs text-red-600 dark:text-red-400">
                            {{ errorFor('price_monthly') }}
                        </p>
                    </div>
                </div>

                <div>
                    <label for="barangay" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Barangay</label>
                    <select
                        id="barangay"
                        v-model="form.barangay"
                        @focus="clearFieldError('barangay')"
                        @change="clearFieldError('barangay')"
                        @blur="validateField('barangay')"
                        :class="inputClassFor('barangay')"
                    >
                        <option value="" disabled>Select barangay...</option>
                        <option v-for="b in barangays" :key="b.value" :value="b.value">{{ b.label }}</option>
                    </select>
                    <p v-if="errorFor('barangay')" class="mt-1 text-xs text-red-600 dark:text-red-400">
                        {{ errorFor('barangay') }}
                    </p>
                </div>

                <FieldEditListingMapPicker />
            </div>
        </div>

        <!-- FULL WIDTH: beds, baths, sqm + amenities -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm p-6 space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="beds" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Bedrooms</label>
                    <input
                        id="beds"
                        v-model.number="form.beds"
                        type="number"
                        min="0"
                        max="20"
                        @focus="clearFieldError('beds')"
                        @blur="validateField('beds')"
                        :class="inputClassFor('beds')"
                    >
                    <p v-if="errorFor('beds')" class="mt-1 text-xs text-red-600 dark:text-red-400">
                        {{ errorFor('beds') }}
                    </p>
                </div>
                <div>
                    <label for="baths" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Bathrooms</label>
                    <input
                        id="baths"
                        v-model.number="form.baths"
                        type="number"
                        min="0"
                        max="20"
                        @focus="clearFieldError('baths')"
                        @blur="validateField('baths')"
                        :class="inputClassFor('baths')"
                    >
                    <p v-if="errorFor('baths')" class="mt-1 text-xs text-red-600 dark:text-red-400">
                        {{ errorFor('baths') }}
                    </p>
                </div>
                <div>
                    <label for="sqm" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Floor area</label>
                    <div class="mt-1 relative">
                        <input
                            id="sqm"
                            v-model.number="form.sqm"
                            type="number"
                            min="0"
                            @focus="clearFieldError('sqm')"
                            @blur="validateField('sqm')"
                            :class="[
                                'w-full rounded-md border bg-white dark:bg-gray-800 px-3.5 pr-12 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-1 outline-none transition',
                                errorFor('sqm') ? inputClassError : inputClassOk,
                            ]"
                        >
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400 dark:text-gray-500 pointer-events-none">sqm</span>
                    </div>
                    <p v-if="errorFor('sqm')" class="mt-1 text-xs text-red-600 dark:text-red-400">
                        {{ errorFor('sqm') }}
                    </p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Amenities</label>
                <div v-if="amenities && amenities.length > 0" class="flex flex-wrap gap-2">
                    <label
                        v-for="amenity in amenities"
                        :key="amenity.id"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium border cursor-pointer transition"
                        :class="form.amenities.includes(amenity.id)
                            ? 'bg-orange-50 dark:bg-orange-950/30 text-orange-700 dark:text-orange-300 border-orange-200 dark:border-orange-900/50'
                            : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:border-orange-300 dark:hover:border-orange-700/60'"
                    >
                        <input
                            type="checkbox"
                            :value="amenity.id"
                            v-model="form.amenities"
                            class="hidden"
                        >
                        {{ amenity.name }}
                    </label>
                </div>
                <p v-else class="text-sm text-gray-500 dark:text-gray-400">No amenities defined.</p>
                <p v-if="amenitiesError" class="mt-2 text-xs text-red-600 dark:text-red-400">
                    {{ amenitiesError }}
                </p>
            </div>
        </div>
    </div>
</template>
