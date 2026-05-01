<script setup>
import { computed } from 'vue';

const props = defineProps({ listing: { type: Object, required: true } });

const formattedSubmittedAt = computed(() => {
    if (!props.listing.visited_at) return '';
    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric', month: 'short', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    }).format(new Date(props.listing.visited_at));
});
</script>

<template>
    <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-5 py-4">
        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3">Source &amp; Notes</h3>

        <dl class="space-y-3 text-sm">
            <!-- Contact -->
            <div v-if="listing.contact_phone" class="flex flex-col">
                <dt class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">Contact</dt>
                <dd class="text-gray-900 dark:text-gray-100">
                    <a :href="'tel:' + listing.contact_phone" class="text-orange-600 dark:text-orange-400 hover:text-orange-700 dark:hover:text-orange-300">{{ listing.contact_phone }}</a>
                    <span v-if="listing.contact_type_label" class="text-gray-500 dark:text-gray-400"> · {{ listing.contact_type_label }}</span>
                </dd>
            </div>

            <!-- Source -->
            <div v-if="listing.source_site_label || listing.source_url" class="flex flex-col">
                <dt class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">Source</dt>
                <dd class="text-gray-900 dark:text-gray-100">
                    <a
                        v-if="listing.source_url"
                        :href="listing.source_url"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-1 text-orange-600 dark:text-orange-400 hover:text-orange-700 dark:hover:text-orange-300"
                    >
                        {{ listing.source_site_label || 'View source' }}
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
                            <polyline points="15 3 21 3 21 9" />
                            <line x1="10" y1="14" x2="21" y2="3" />
                        </svg>
                    </a>
                    <span v-else>{{ listing.source_site_label }}</span>
                </dd>
            </div>

            <!-- Directions -->
            <div v-if="listing.directions" class="flex flex-col">
                <dt class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">Directions</dt>
                <dd class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ listing.directions }}</dd>
            </div>

            <!-- Verification notes (calls-team-set) -->
            <div v-if="listing.verification_notes" class="flex flex-col">
                <dt class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">Notes</dt>
                <dd class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ listing.verification_notes }}</dd>
            </div>
        </dl>

        <p v-if="formattedSubmittedAt" class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-800 text-xs text-gray-500 dark:text-gray-400">
            Submitted {{ formattedSubmittedAt }}
        </p>
    </div>
</template>
