<script setup>
import { ref, computed, watch, nextTick } from 'vue';

const props = defineProps({
    listing: { type: Object, required: true },
});

const activeIndex = ref(0);
const images = computed(() => props.listing?.images || []);
const activeImage = computed(() => images.value[activeIndex.value]);

const thumbStrip = ref(null);

function prev() {
    const n = images.value.length;
    if (n < 2) return;
    activeIndex.value = (activeIndex.value - 1 + n) % n;
}
function next() {
    const n = images.value.length;
    if (n < 2) return;
    activeIndex.value = (activeIndex.value + 1) % n;
}

const imgTouch = { x: 0, y: 0, time: 0, valid: false };

function onImageTouchStart(e) {
    if (e.touches.length !== 1) {
        imgTouch.valid = false;
        return;
    }
    const t = e.touches[0];
    imgTouch.x = t.clientX;
    imgTouch.y = t.clientY;
    imgTouch.time = Date.now();
    imgTouch.valid = true;
}

function onImageTouchEnd(e) {
    if (!imgTouch.valid) return;
    imgTouch.valid = false;
    if (images.value.length < 2) return;
    const t = e.changedTouches[0];
    const dx = t.clientX - imgTouch.x;
    const dy = t.clientY - imgTouch.y;
    const dt = Date.now() - imgTouch.time;
    if (Math.abs(dx) > 50 && Math.abs(dy) < 60 && dt < 600) {
        if (dx < 0) next();
        else prev();
    }
}

watch(activeIndex, async () => {
    await nextTick();
    const strip = thumbStrip.value;
    if (!strip) return;
    const active = strip.children[activeIndex.value];
    if (!active) return;
    const target = active.offsetLeft - (strip.clientWidth - active.clientWidth) / 2;
    strip.scrollTo({ left: Math.max(0, target), behavior: 'smooth' });
});
</script>

<template>
    <div>
        <div
            class="relative aspect-video bg-gray-100 dark:bg-gray-800 rounded-2xl overflow-hidden"
            @touchstart.passive.stop="onImageTouchStart"
            @touchend.passive.stop="onImageTouchEnd"
        >
            <img
                v-if="activeImage"
                :src="activeImage.url"
                :alt="listing.title"
                class="w-full h-full object-cover"
            />
            <button
                v-if="images.length > 1"
                @click="prev"
                aria-label="Previous image"
                class="absolute top-1/2 -translate-y-1/2 left-2 md:left-3 w-7 h-7 md:w-10 md:h-10 rounded-full bg-black/40 hover:bg-black/60 text-white inline-flex items-center justify-center transition cursor-pointer"
            >
                <svg class="w-4 h-4 md:w-5 md:h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6" /></svg>
            </button>
            <button
                v-if="images.length > 1"
                @click="next"
                aria-label="Next image"
                class="absolute top-1/2 -translate-y-1/2 right-2 md:right-3 w-7 h-7 md:w-10 md:h-10 rounded-full bg-black/40 hover:bg-black/60 text-white inline-flex items-center justify-center transition cursor-pointer"
            >
                <svg class="w-4 h-4 md:w-5 md:h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6" /></svg>
            </button>
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
                        'rounded-full transition cursor-pointer',
                        i === activeIndex ? 'w-6 h-2 bg-white' : 'w-2 h-2 bg-white/60 hover:bg-white/80',
                    ]"
                ></button>
            </div>
        </div>

        <div
            ref="thumbStrip"
            v-if="images.length > 1"
            class="mt-3 flex gap-3 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
            @touchstart.passive="onImageTouchStart"
            @touchend.passive="onImageTouchEnd"
        >
            <button
                v-for="(img, i) in images"
                :key="img.id"
                @click="activeIndex = i"
                :class="[
                    'shrink-0 w-16 h-11 md:w-24 md:h-16 rounded-lg overflow-hidden border-2 transition cursor-pointer',
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
