<script setup>
import { ref, computed } from 'vue';

const props = defineProps({ photos: { type: Array, required: true } });

const activeIndex = ref(0);
const hero = computed(() => props.photos[activeIndex.value] ?? null);
</script>

<template>
    <div v-if="photos.length" class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
        <div class="bg-gray-100 dark:bg-gray-800 h-64 sm:h-72 lg:h-80">
            <img v-if="hero" :src="hero.url" alt="" class="w-full h-full object-cover" />
        </div>
        <div v-if="photos.length > 1" class="flex gap-2 p-3 overflow-x-auto">
            <button
                v-for="(p, i) in photos"
                :key="p.id"
                type="button"
                @click="activeIndex = i"
                :class="[
                    'w-20 h-14 rounded-md overflow-hidden border-2 shrink-0 cursor-pointer',
                    i === activeIndex
                        ? 'border-orange-500'
                        : 'border-transparent opacity-70 hover:opacity-100',
                ]"
            >
                <img :src="p.url" alt="" class="w-full h-full object-cover" />
            </button>
        </div>
    </div>
    <div v-else class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-6 py-8 text-center text-sm text-gray-400">
        No photos
    </div>
</template>
