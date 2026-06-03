<script setup>
defineProps({
    size: { type: String, default: 'md' },
});

const sizes = {
    md: {
        boxPx: 28,
        boxRadius: '6.72px',
        iconPx: 16.24,
        wordmark: 'text-base leading-none',
        badge: 'w-[11px] h-[11px] -top-[2.42px] -right-[2.42px]',
        badgeIconPx: 6.6,
        gap: 'gap-2.5',
    },
    lg: {
        boxPx: 56,
        boxRadius: '13.44px',
        iconPx: 32.48,
        wordmark: 'text-3xl leading-none',
        badge: 'w-[18px] h-[18px] -top-[4px] -right-[4px]',
        badgeIconPx: 11,
        gap: 'gap-3',
    },
};
</script>

<template>
    <div :class="['inline-flex items-center', sizes[size].gap]">
        <div
            class="relative shrink-0"
            :style="`width: ${sizes[size].boxPx}px; height: ${sizes[size].boxPx}px;`"
        >
            <div
                class="w-full h-full flex items-center justify-center"
                :style="`
                    background: linear-gradient(rgb(251, 146, 60) 0%, rgb(249, 115, 22) 100%);
                    border-radius: ${sizes[size].boxRadius};
                    box-shadow: inset 0 -2px 0 rgba(0,0,0,0.08), 0 1px 2px rgba(249,115,22,0.3);
                `"
            >
                <svg
                    :width="sizes[size].iconPx"
                    :height="sizes[size].iconPx"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="#fff"
                    stroke-width="2.4"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M3 10.5 12 3l9 7.5" />
                    <path d="M5 9.5V20a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9.5" fill="#fff" stroke="none" opacity=".18" />
                    <path d="M5 9.5V20a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9.5" />
                </svg>
            </div>
            <span
                :class="['absolute rounded-full bg-emerald-500 ring-2 ring-white dark:ring-gray-900 flex items-center justify-center', sizes[size].badge]"
            >
                <svg
                    :width="sizes[size].badgeIconPx"
                    :height="sizes[size].badgeIconPx"
                    viewBox="0 0 16 16"
                    fill="none"
                    stroke="#fff"
                    stroke-width="3"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="m3 8 3.5 3.5L13 5" />
                </svg>
            </span>
        </div>
        <span :class="[sizes[size].wordmark, 'font-bold tracking-tight text-gray-900 dark:text-white']">
            RentConnect<span class="text-orange-500">PH</span>
        </span>
    </div>
</template>
