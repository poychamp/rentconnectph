<script setup>
import { computed } from 'vue';

const props = defineProps({
    listingTypes: { type: Array, default: () => [] },
    active: { type: String, default: 'All' },
});
const emit = defineEmits(['change']);

const types = computed(() => [
    { value: 'All', label: 'All' },
    ...props.listingTypes,
]);
</script>

<template>
    <div class="border-b border-gray-100 bg-white dark:bg-gray-900 dark:border-gray-800">
        <div class="max-w-7xl mx-auto px-4 md:px-6 lg:px-8">
            <div class="flex flex-wrap gap-2 py-4">
                <button
                    v-for="type in types"
                    :key="type.value"
                    @click="emit('change', type.value)"
                    :class="[
                        'px-4 py-2 rounded-full text-sm font-medium transition border',
                        active === type.value
                            ? 'bg-orange-500 text-white border-orange-500'
                            : 'bg-white text-gray-700 border-gray-200 hover:border-gray-300 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700 dark:hover:border-gray-600',
                    ]"
                >
                    {{ type.label === 'Apartment' ? 'Apartments' : type.label }}
                </button>
            </div>
        </div>
    </div>
</template>
