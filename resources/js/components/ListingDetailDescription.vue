<script setup>
defineProps({
    listing: { type: Object, required: true },
});
</script>

<template>
    <section>
        <h2 class="text-xl md:text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
            About this property
        </h2>
        <div class="mt-3 text-sm md:text-base text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-line">
            {{ listing.description }}
        </div>
    </section>
</template>
