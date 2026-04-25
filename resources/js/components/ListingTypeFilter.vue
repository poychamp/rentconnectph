<script setup>
defineProps({
    active: { type: String, default: 'All' },
});
const emit = defineEmits(['change']);

const types = ['All', 'Apartment', 'Studio', 'House', 'Condo', 'Bedspacer'];
</script>

<template>
    <div class="border-b border-gray-100 bg-white dark:bg-gray-900 dark:border-gray-800">
        <div class="max-w-7xl mx-auto px-4 md:px-6 lg:px-8">
            <div class="flex gap-2 overflow-x-auto py-4 -mx-1 px-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden md:overflow-visible md:flex-wrap">
                <button
                    v-for="type in types"
                    :key="type"
                    @click="emit('change', type)"
                    :class="[
                        'shrink-0 px-4 py-2 rounded-full text-sm font-medium transition border',
                        active === type
                            ? 'bg-orange-500 text-white border-orange-500'
                            : 'bg-white text-gray-700 border-gray-200 hover:border-gray-300 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700 dark:hover:border-gray-600',
                    ]"
                >
                    {{ type === 'Apartment' ? 'Apartments' : type }}
                </button>
            </div>
        </div>
    </div>
</template>
