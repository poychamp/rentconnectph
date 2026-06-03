<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    action: { type: String, required: true },
    method: { type: String, default: 'POST', validator: (v) => ['POST', 'PUT'].includes(v) },
    initial: {
        type: Object,
        default: () => ({ name: '', slug: '' }),
    },
    errors: { type: Object, default: () => ({}) },
    submitLabel: { type: String, default: 'Save' },
    lockSlug: { type: Boolean, default: false },
});

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const form = ref({
    name: props.initial.name ?? '',
    slug: props.initial.slug ?? '',
});

// Slug is always derived from the name — admin can't override.
function slugify(value) {
    return String(value)
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '');
}

// =====================================================================
// Client-side validation bundle
// =====================================================================

// Toggle: when false, all client-side checks no-op. Native submit fires;
// server validates; errors round-trip back via Blade @json injection and
// render through fieldError(). Flip back to true to re-enable pre-flight.
const CLIENT_VALIDATION_ENABLED = true;

// Validators mirror the server-side rules in `Admin\AmenityController`.
// Each returns either an error message string OR null when the field is OK.
// Server still validates on submit — these are pre-flight defenses for UX,
// not the source of truth.
const VALIDATORS = {
    name: (v) => {
        if (!v || v.trim() === '') return 'Name is required.';
        if (v.length > 80) return 'Name is too long (max 80 characters).';
        return null;
    },
    slug: (v) => {
        if (!v || v.trim() === '') return 'Slug is required.';
        if (v.length > 32) return 'Slug is too long (max 32 characters).';
        if (!/^[a-z][a-z0-9_]*$/.test(v)) {
            return 'Slug must be snake_case (lowercase letters, digits, underscores; starts with a letter).';
        }
        return null;
    },
};

// Hydrate from server errors (flatten array-shape Laravel error bag).
function flattenErrors(serverErrors) {
    if (!serverErrors) return {};
    const out = {};
    for (const key of Object.keys(serverErrors)) {
        out[key] = Array.isArray(serverErrors[key]) ? serverErrors[key][0] : serverErrors[key];
    }
    return out;
}

const errors = ref(flattenErrors(props.errors));

function validateField(name) {
    if (!CLIENT_VALIDATION_ENABLED) return;
    const err = VALIDATORS[name]?.(form.value[name]);
    if (err) {
        errors.value = { ...errors.value, [name]: err };
    } else {
        const next = { ...errors.value };
        delete next[name];
        errors.value = next;
    }
}

function clearFieldError(name) {
    if (!(name in errors.value)) return;
    const next = { ...errors.value };
    delete next[name];
    errors.value = next;
}

function validateAll() {
    if (!CLIENT_VALIDATION_ENABLED) return true;
    Object.keys(VALIDATORS).forEach(validateField);
    return Object.keys(errors.value).length === 0;
}

function fieldError(name) {
    return errors.value[name] ?? null;
}

function onSubmit(event) {
    if (!validateAll()) {
        event.preventDefault();
        document.querySelector('main')?.scrollTo({ top: 0, behavior: 'smooth' });
    }
    // else: native form submit proceeds.
}

// Slug auto-derives from name on create. On edit (`lockSlug`), the persisted
// slug is the iconPaths key — renaming would orphan the glyph mapping. The
// edit form ships the slug back via hidden input so it stays in the payload.
watch(() => form.value.name, (next) => {
    if (props.lockSlug) return;
    form.value.slug = slugify(next);
    if ('slug' in errors.value) {
        validateField('slug');
    }
});
</script>

<template>
    <form :action="action" method="POST" class="space-y-6" @submit="onSubmit">
        <input type="hidden" name="_token" :value="csrfToken">
        <input v-if="method !== 'POST'" type="hidden" name="_method" :value="method">

        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm p-6 space-y-5">
            <!-- Name -->
            <div>
                <label for="amenity-name" class="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">
                    Name <span class="text-rose-500">*</span>
                </label>
                <input
                    id="amenity-name"
                    name="name"
                    type="text"
                    v-model="form.name"
                    @blur="validateField('name')"
                    @focus="clearFieldError('name')"
                    placeholder="e.g. WiFi, Parking, Pets"
                    class="w-full px-3 py-2 rounded-md border bg-white dark:bg-gray-950 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-orange-500"
                    :class="fieldError('name')
                        ? 'border-rose-500 focus:ring-rose-500'
                        : 'border-gray-300 dark:border-gray-700'"
                >
                <p v-if="fieldError('name')" class="mt-1 text-xs text-rose-600 dark:text-rose-400">
                    {{ fieldError('name') }}
                </p>
                <p v-else class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Display name renters and admin see.
                </p>
            </div>

            <!-- Slug (readonly — auto-derived from name) -->
            <div>
                <label for="amenity-slug" class="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">
                    Slug
                </label>
                <input
                    id="amenity-slug"
                    name="slug"
                    type="text"
                    :value="form.slug"
                    readonly
                    placeholder="auto-derived from name"
                    class="w-full px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-800/50 text-gray-700 dark:text-gray-300 placeholder-gray-400 font-mono text-sm cursor-not-allowed focus:outline-none"
                    :class="fieldError('slug')
                        ? 'border-rose-500'
                        : 'border-gray-200 dark:border-gray-700'"
                >
                <p v-if="fieldError('slug')" class="mt-1 text-xs text-rose-600 dark:text-rose-400">
                    {{ fieldError('slug') }}
                </p>
                <p v-else-if="lockSlug" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Locked. The slug maps to the icon glyph and can't be renamed once created.
                </p>
                <p v-else class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Stable identifier. Auto-derived from name (snake_case, lowercase).
                </p>
            </div>
        </div>

        <!-- Action bar -->
        <div class="flex items-center justify-between gap-3">
            <a
                href="/admin/amenities"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-md border border-gray-300 dark:border-gray-700 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
            >
                Cancel
            </a>
            <button
                type="submit"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-orange-500 text-white text-sm font-semibold hover:bg-orange-600 active:bg-orange-700 transition cursor-pointer"
            >
                {{ submitLabel }}
            </button>
        </div>
    </form>
</template>
