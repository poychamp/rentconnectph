<script setup>
import { ref, watch, onUnmounted, inject } from 'vue';

const MAX_PHOTOS = 20;

const form = inject('addListingForm');

// Each photo: { id (local random), file, url (objectURL), key (S3 key, set by Vapor.store in Phase 2) }
// Phase 1 placeholder: key is null until Vapor wiring lands; we still mirror photos.length and the
// local-preview URLs into form.photos so the submit dump shows order + filename intent.
const photos = ref([]);
const fileInputRef = ref(null);
const dragIndex = ref(null);
const isDraggingFile = ref(false);

// Mirror the photo list into the shared form payload as an array of { name, key } objects.
// When Vapor.store() lands in Phase 2, `key` becomes the real S3 path and the dump
// will show the actual storage references.
watch(photos, (list) => {
    form.photos = list.map(p => ({
        name: p.file?.name ?? null,
        size: p.file?.size ?? null,
        key:  p.key ?? null,         // Phase 2: populated by Vapor.store(file).then(r => r.key)
    }));
}, { deep: true });

function openPicker() {
    fileInputRef.value?.click();
}

function onDropFiles(event) {
    isDraggingFile.value = false;
    const files = Array.from(event.dataTransfer?.files ?? []);
    addFiles(files);
}

function onDragEnterFiles(event) {
    if (event.dataTransfer?.types?.includes('Files')) {
        isDraggingFile.value = true;
    }
}

function onDragLeaveFiles(event) {
    // Only clear if we left the dropzone entirely (not just moved to a child)
    if (!event.currentTarget.contains(event.relatedTarget)) {
        isDraggingFile.value = false;
    }
}

function onFilesPicked(event) {
    const files = Array.from(event.target.files ?? []);
    addFiles(files);
    event.target.value = '';   // reset so picking the same file twice still triggers @change
}

function addFiles(files) {
    const remaining = MAX_PHOTOS - photos.value.length;
    files.slice(0, remaining)
        .filter(f => f.type.startsWith('image/'))
        .forEach(file => {
            photos.value.push({
                id:   crypto.randomUUID(),
                file,
                url:  URL.createObjectURL(file),
            });
        });
}

function remove(id) {
    const idx = photos.value.findIndex(p => p.id === id);
    if (idx === -1) return;
    URL.revokeObjectURL(photos.value[idx].url);
    photos.value.splice(idx, 1);
}

// Drag-to-reorder
function onDragStart(idx) { dragIndex.value = idx; }
function onDragOver(event) { event.preventDefault(); }
function onDrop(targetIdx) {
    if (dragIndex.value === null || dragIndex.value === targetIdx) return;
    const moved = photos.value.splice(dragIndex.value, 1)[0];
    photos.value.splice(targetIdx, 0, moved);
    dragIndex.value = null;
}

onUnmounted(() => {
    photos.value.forEach(p => URL.revokeObjectURL(p.url));
});
</script>

<template>
    <div>
        <div class="flex items-baseline justify-between">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Photos
            </label>
            <span class="text-xs text-gray-400 dark:text-gray-500">
                {{ photos.length }}/{{ MAX_PHOTOS }} · drag to reorder · first is cover
            </span>
        </div>

        <input
            ref="fileInputRef"
            type="file"
            accept="image/*"
            multiple
            class="hidden"
            @change="onFilesPicked"
        >

        <button
            type="button"
            @click="openPicker"
            @dragenter.prevent="onDragEnterFiles"
            @dragover.prevent
            @dragleave="onDragLeaveFiles"
            @drop.prevent="onDropFiles"
            :disabled="photos.length >= MAX_PHOTOS"
            style="border-width: 1.5px;"
            :class="[
                'mt-1 w-full text-center rounded-[10px] border-dashed px-4 py-3.5 transition cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed',
                isDraggingFile
                    ? 'border-orange-400 bg-orange-50 dark:border-orange-500 dark:bg-orange-950/30'
                    : 'border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/50 hover:bg-gray-100 dark:hover:bg-gray-800',
            ]"
        >
            <span class="inline-flex items-center gap-2.5 text-[13px] text-gray-600 dark:text-gray-300">
                <svg class="w-4 h-4 shrink-0 text-orange-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="16" rx="2"/>
                    <circle cx="9" cy="10" r="2"/>
                    <path d="m3 18 6-6 4 4 3-3 5 5"/>
                </svg>
                <span>
                    <span class="font-semibold text-gray-900 dark:text-white">
                        {{ photos.length === 0 ? 'Add photos' : photos.length >= MAX_PHOTOS ? 'Maximum reached' : 'Add more photos' }}
                    </span>
                    <span class="text-gray-400 dark:text-gray-500">
                        {{ photos.length >= MAX_PHOTOS ? '' : ' or click to browse' }}
                    </span>
                    <span class="ml-2 text-[11.5px] text-gray-400 dark:text-gray-500">
                        · {{ photos.length }}/{{ MAX_PHOTOS }}
                    </span>
                </span>
            </span>
        </button>

        <ul
            v-if="photos.length > 0"
            class="mt-3 grid grid-cols-3 gap-2"
        >
            <li
                v-for="(photo, idx) in photos"
                :key="photo.id"
                draggable="true"
                @dragstart="onDragStart(idx)"
                @dragover="onDragOver"
                @drop="onDrop(idx)"
                class="relative aspect-square rounded-md overflow-hidden border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 cursor-move"
            >
                <img
                    :src="photo.url"
                    :alt="'Photo ' + (idx + 1)"
                    class="w-full h-full object-cover"
                >
                <span
                    v-if="idx === 0"
                    class="absolute top-1.5 left-1.5 inline-flex items-center gap-1 rounded bg-gray-900/85 text-white text-[10px] font-semibold uppercase tracking-wider px-1.5 py-0.5"
                >
                    Cover
                </span>
                <button
                    type="button"
                    @click="remove(photo.id)"
                    class="absolute top-1 right-1 w-5 h-5 flex items-center justify-center rounded-full bg-gray-900/85 text-white hover:bg-gray-900 transition cursor-pointer"
                    :title="'Remove photo ' + (idx + 1)"
                >
                    <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round">
                        <path d="M18 6 6 18M6 6l12 12"/>
                    </svg>
                </button>
            </li>
        </ul>
    </div>
</template>
