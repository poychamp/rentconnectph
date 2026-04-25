<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    listing: { type: Object, required: true },
});

const activeIndex = ref(0);
const images = computed(() => props.listing?.images || []);
const activeImage = computed(() => images.value[activeIndex.value]);
</script>

<template>
    <div>
        <div class="relative aspect-video bg-gray-100 dark:bg-gray-800 rounded-2xl overflow-hidden">
            <img
                v-if="activeImage"
                :src="activeImage.url"
                :alt="listing.title"
                class="w-full h-full object-cover"
            />
            <div
                v-if="images.length > 1"
                class="absolute bottom-3 left-1/2 -translate-x-1/2 flex items-center gap-1.5"
            >
                <button
                    v-for="(img, i) in images"
                    :key="img.id"
                    @click="activeIndex = i"
                    :aria-label="`Show image ${i + 1}`"
                    :class="[
                        'rounded-full transition',
                        i === activeIndex ? 'w-6 h-2 bg-white' : 'w-2 h-2 bg-white/60 hover:bg-white/80',
                    ]"
                ></button>
            </div>
        </div>

        <div v-if="images.length > 1" class="mt-3 flex gap-3 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            <button
                v-for="(img, i) in images"
                :key="img.id"
                @click="activeIndex = i"
                :class="[
                    'shrink-0 w-24 h-16 rounded-lg overflow-hidden border-2 transition',
                    i === activeIndex
                        ? 'border-orange-500'
                        : 'border-transparent opacity-70 hover:opacity-100',
                ]"
            >
                <img :src="img.url" alt="" class="w-full h-full object-cover" />
            </button>
        </div>
    </div>
</template>
