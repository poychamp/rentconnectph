<script setup>
import { computed } from 'vue';

const props = defineProps({
    listing: { type: Object, required: true },
});

const hasDescription = computed(() => {
    const d = props.listing?.description;
    return typeof d === 'string' && d.trim() !== '';
});
</script>

<template>
    <section v-if="hasDescription">
        <h2 class="text-xl md:text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
            About this property
        </h2>
        <div class="mt-3 text-sm md:text-base text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-line">
            {{ listing.description }}
        </div>
    </section>
</template>
