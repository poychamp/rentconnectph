<script setup>
const props = defineProps({
    modelValue: { type: Number, default: 0 },
    label:    { type: String, required: true },
    required: { type: Boolean, default: false },
    min:      { type: Number, default: 0 },
    max:      { type: Number, default: null },
    suffix:   { type: String, default: null },
});
const emit = defineEmits(['update:modelValue']);

function dec() {
    const next = Math.max(props.min, (Number(props.modelValue) || 0) - 1);
    emit('update:modelValue', next);
}
function inc() {
    const current = Number(props.modelValue) || 0;
    const next = props.max != null ? Math.min(props.max, current + 1) : current + 1;
    emit('update:modelValue', next);
}
function onTyped(event) {
    const raw = event.target.value;
    if (raw === '') {
        emit('update:modelValue', props.min);
        return;
    }
    let n = Number(raw);
    if (Number.isNaN(n)) return;
    n = Math.max(props.min, n);
    if (props.max != null) n = Math.min(props.max, n);
    emit('update:modelValue', n);
}
</script>

<template>
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ label }}<span v-if="required" class="text-red-500"> *</span>
        </label>
        <div class="mt-1 flex items-center justify-between rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-2.5 py-1.5">
            <button
                type="button"
                @click="dec"
                class="w-7 h-7 flex items-center justify-center rounded text-gray-500 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer transition"
                :title="'Decrease ' + label.toLowerCase()"
            >
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <path d="M5 12h14"/>
                </svg>
            </button>

            <div class="flex-1 mx-2 flex items-center justify-center gap-1">
                <input
                    type="number"
                    :value="modelValue"
                    @input="onTyped"
                    :min="min"
                    :max="max ?? undefined"
                    class="w-full bg-transparent text-center text-sm text-gray-900 dark:text-white font-medium tabular-nums focus:outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                >
                <span v-if="suffix" class="text-xs text-gray-400 font-normal">{{ suffix }}</span>
            </div>

            <button
                type="button"
                @click="inc"
                class="w-7 h-7 flex items-center justify-center rounded text-gray-500 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer transition"
                :title="'Increase ' + label.toLowerCase()"
            >
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <path d="M12 5v14M5 12h14"/>
                </svg>
            </button>
        </div>
    </div>
</template>
