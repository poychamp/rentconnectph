<script setup>
import { computed, inject, ref } from 'vue';
import AdminNumberStepper from './AdminNumberStepper.vue';
import AdminAddListingAmenityPills from './AdminAddListingAmenityPills.vue';
import AdminAddListingPhotoUpload from './AdminAddListingPhotoUpload.vue';
import AdminAddListingMapPicker from './AdminAddListingMapPicker.vue';

defineProps({
    listingTypes: { type: Array, required: true },
    barangays:    { type: Array, required: true },
    amenities:    { type: Array, required: true },
});

const form = inject('addListingForm');
const errors = inject('addListingFormErrors', ref({}));
const { validateField, clearFieldError } = inject('addListingFormValidate', {
    validateField: () => true,
    clearFieldError: () => {},
});

// First message for a given field, or null. Laravel returns string[] per key.
const errorFor = (field) => errors.value?.[field]?.[0] ?? null;

// Tailwind classes appended to an input when its field has an error — swaps
// the gray border for red and shifts the focus ring to red so it's obvious.
const inputErrorClass = 'border-red-400 dark:border-red-500 focus:border-red-500 focus:ring-red-500';
</script>

<template>
    <div class="space-y-5">
        <!-- TOP ROW: two 50/50 cards side by side -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <!-- CARD 1 (LEFT 50%) -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm p-6 space-y-5">
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Title <span class="text-red-500">*</span>
                    </label>
                    <input
                        id="title"
                        v-model="form.title"
                        type="text"
                        placeholder="e.g. Modern 2-BR Apartment in Pueblo de Oro"
                        @focus="clearFieldError('title')"
                        @blur="validateField('title')"
                        :class="[
                            'mt-1 w-full rounded-md border bg-white dark:bg-gray-800 px-3.5 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:ring-1 outline-none transition',
                            errorFor('title')
                                ? inputErrorClass
                                : 'border-gray-200 dark:border-gray-700 focus:border-orange-500 focus:ring-orange-500',
                        ]"
                    >
                    <p v-if="errorFor('title')" class="mt-1 text-xs text-red-600 dark:text-red-400">
                        {{ errorFor('title') }}
                    </p>
                </div>

                <div>
                    <div class="flex items-baseline justify-between">
                        <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Description
                        </label>
                        <span class="text-xs text-gray-400 dark:text-gray-500">optional</span>
                    </div>
                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="4"
                        placeholder="Brief description — what makes this place special, nearby landmarks, etc."
                        :class="[
                            'mt-1 w-full rounded-md border bg-white dark:bg-gray-800 px-3.5 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:ring-1 outline-none transition resize-y',
                            errorFor('description')
                                ? inputErrorClass
                                : 'border-gray-200 dark:border-gray-700 focus:border-orange-500 focus:ring-orange-500',
                        ]"
                    ></textarea>
                    <p v-if="errorFor('description')" class="mt-1 text-xs text-red-600 dark:text-red-400">
                        {{ errorFor('description') }}
                    </p>
                </div>

                <AdminAddListingPhotoUpload />
            </div>

            <!-- CARD 2 (RIGHT 50%) -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm p-6 space-y-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="listing-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Listing type <span class="text-red-500">*</span>
                        </label>
                        <select
                            id="listing-type"
                            v-model="form.listing_type"
                            @focus="clearFieldError('listing_type')"
                            @change="validateField('listing_type')"
                            @blur="validateField('listing_type')"
                            :class="[
                                'mt-1 w-full rounded-md border bg-white dark:bg-gray-800 px-3.5 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-1 outline-none transition',
                                errorFor('listing_type')
                                    ? inputErrorClass
                                    : 'border-gray-200 dark:border-gray-700 focus:border-orange-500 focus:ring-orange-500',
                            ]"
                        >
                            <option value="" disabled>Select type...</option>
                            <option v-for="t in listingTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                        <p v-if="errorFor('listing_type')" class="mt-1 text-xs text-red-600 dark:text-red-400">
                            {{ errorFor('listing_type') }}
                        </p>
                    </div>

                    <div>
                        <label for="monthly-rent" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Monthly rent <span class="text-red-500">*</span>
                        </label>
                        <div class="mt-1 relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-500 dark:text-gray-400 pointer-events-none">₱</span>
                            <input
                                id="monthly-rent"
                                v-model.number="form.monthly_rent"
                                type="number"
                                min="0"
                                placeholder="0"
                                @focus="clearFieldError('monthly_rent')"
                                @blur="validateField('monthly_rent')"
                                :class="[
                                    'w-full rounded-md border bg-white dark:bg-gray-800 pl-7 pr-16 py-2.5 text-sm text-right text-gray-900 dark:text-white placeholder-gray-400 focus:ring-1 outline-none transition',
                                    errorFor('monthly_rent')
                                        ? inputErrorClass
                                        : 'border-gray-200 dark:border-gray-700 focus:border-orange-500 focus:ring-orange-500',
                                ]"
                            >
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400 dark:text-gray-500 pointer-events-none">/ month</span>
                        </div>
                        <p v-if="errorFor('monthly_rent')" class="mt-1 text-xs text-red-600 dark:text-red-400">
                            {{ errorFor('monthly_rent') }}
                        </p>
                    </div>
                </div>

                <div>
                    <label for="barangay" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Barangay <span class="text-red-500">*</span>
                    </label>
                    <select
                        id="barangay"
                        v-model="form.barangay"
                        @focus="clearFieldError('barangay')"
                        @change="validateField('barangay')"
                        @blur="validateField('barangay')"
                        :class="[
                            'mt-1 w-full rounded-md border bg-white dark:bg-gray-800 px-3.5 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-1 outline-none transition',
                            errorFor('barangay')
                                ? inputErrorClass
                                : 'border-gray-200 dark:border-gray-700 focus:border-orange-500 focus:ring-orange-500',
                        ]"
                    >
                        <option value="" disabled>Select barangay...</option>
                        <option v-for="b in barangays" :key="b.value" :value="b.value">{{ b.label }}</option>
                    </select>
                    <p v-if="errorFor('barangay')" class="mt-1 text-xs text-red-600 dark:text-red-400">
                        {{ errorFor('barangay') }}
                    </p>
                </div>

                <AdminAddListingMapPicker />
            </div>
        </div>

        <!-- CARD 3 (FULL WIDTH BELOW) -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm p-6 space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <AdminNumberStepper
                    v-model="form.beds"
                    label="Bedrooms"
                    required
                    :error="errorFor('beds')"
                    @focus="clearFieldError('beds')"
                    @blur="validateField('beds')"
                />
                <AdminNumberStepper
                    v-model="form.baths"
                    label="Bathrooms"
                    required
                    :error="errorFor('baths')"
                    @focus="clearFieldError('baths')"
                    @blur="validateField('baths')"
                />
                <AdminNumberStepper
                    v-model="form.sqft"
                    label="Floor area"
                    suffix="sqft"
                    required
                    :error="errorFor('sqft')"
                    @focus="clearFieldError('sqft')"
                    @blur="validateField('sqft')"
                />
            </div>

            <AdminAddListingAmenityPills :amenities="amenities" v-model="form.amenities" />
        </div>
    </div>
</template>
