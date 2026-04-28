<script setup>
import draggable from 'vuedraggable';
import AdminFeaturedListingsRow from './AdminFeaturedListingsRow.vue';

const props = defineProps({
    rows:   { type: Array,   required: true },
    saving: { type: Boolean, default: false },
});

const emit = defineEmits(['reorder']);

function onUpdateModel(newList) {
    emit('reorder', newList);
}
</script>

<template>
    <div class="relative bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm overflow-hidden">
        <!-- Top loading bar — visible while a save is in flight -->
        <div v-if="saving" class="absolute inset-x-0 top-0 h-0.5 overflow-hidden z-10">
            <div class="h-full w-1/3 bg-sky-500 dark:bg-sky-400 animate-[loading-slide_1.2s_ease-in-out_infinite]"></div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-950/50 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="px-3 py-3 w-10"></th>
                        <th class="px-4 py-3 w-20">Photo</th>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Barangay</th>
                        <th class="px-4 py-3">Price</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <draggable
                    tag="tbody"
                    :model-value="rows"
                    @update:model-value="onUpdateModel"
                    item-key="uuid"
                    handle=".drag-handle"
                    ghost-class="featured-ghost"
                    drag-class="featured-drag"
                    :animation="180"
                    :force-fallback="true"
                    :scroll-sensitivity="80"
                    :scroll-speed="14"
                    :bubble-scroll="true"
                >
                    <template #item="{ element }">
                        <AdminFeaturedListingsRow :row="element" />
                    </template>
                </draggable>
            </table>
        </div>
    </div>
</template>

<style>
@keyframes loading-slide {
    0%   { transform: translateX(-100%); }
    50%  { transform: translateX(150%); }
    100% { transform: translateX(400%); }
}
.featured-ghost {
    opacity: 0.35;
}
.featured-drag {
    box-shadow: 0 8px 24px rgba(2, 132, 199, 0.25);
}
</style>
