<script setup>
import { ref } from 'vue';

const query = ref('');
const budget = ref('');
const area = ref('');

const budgets = ['Any', '< ₱10,000', '₱10,000 – ₱20,000', '₱20,000 – ₱30,000', '> ₱30,000'];
const areas = ['Any', 'Carmen', 'Kauswagan', 'Lapasan', 'Macasandig', 'Nazareth', 'Pueblo de Oro', 'Indahag', 'Uptown'];
</script>

<template>
    <section
        class="relative isolate overflow-hidden text-white"
        :style="{
            backgroundImage:
                'linear-gradient(rgba(0,0,0,0.55), rgba(0,0,0,0.55)), url(https://placehold.co/1920x900/1a1a1a/ffffff?text=CDO)',
            backgroundSize: 'cover',
            backgroundPosition: 'center',
        }"
    >
        <div class="max-w-7xl mx-auto px-4 md:px-6 lg:px-8 py-12 md:py-20 lg:py-28">
            <p class="text-xs md:text-sm font-semibold tracking-widest uppercase text-orange-400">
                <span class="md:hidden">Cagayan de Oro</span>
                <span class="hidden md:inline">Cagayan de Oro · Misamis Oriental</span>
            </p>
            <h1 class="mt-3 text-3xl md:text-5xl lg:text-6xl font-bold leading-tight max-w-3xl">
                Find your next home in CDO
            </h1>
            <p class="mt-4 text-base md:text-lg text-gray-200 max-w-2xl">
                <span class="md:hidden">Verified listings you can trust.</span>
                <span class="hidden md:inline">Verified listings you can trust. Every property is hand-checked by our licensed broker before it goes live.</span>
            </p>

            <div class="mt-8 bg-white text-gray-900 rounded-2xl p-3 md:p-2 md:pl-4 flex flex-col md:flex-row md:items-center gap-2 shadow-xl max-w-3xl">
                <input
                    v-model="query"
                    type="text"
                    placeholder="Search by area, type, or price..."
                    class="flex-1 bg-transparent outline-none px-2 py-2 text-sm md:text-base"
                />
                <div class="flex gap-2 md:contents">
                    <select v-model="budget" class="flex-1 md:flex-none md:w-40 bg-gray-50 md:bg-transparent rounded-lg px-3 py-2 text-sm border border-gray-200 md:border-0 outline-none">
                        <option value="">Budget</option>
                        <option v-for="b in budgets" :key="b" :value="b">{{ b }}</option>
                    </select>
                    <select v-model="area" class="flex-1 md:flex-none md:w-44 bg-gray-50 md:bg-transparent rounded-lg px-3 py-2 text-sm border border-gray-200 md:border-0 outline-none">
                        <option value="">{{ 'CDO Areas' }}</option>
                        <option v-for="a in areas" :key="a" :value="a">{{ a }}</option>
                    </select>
                </div>
                <a
                    href="#"
                    @click.prevent
                    class="hidden md:inline-flex items-center justify-center bg-orange-500 hover:bg-orange-600 text-white font-medium rounded-xl px-6 py-3 text-sm transition"
                >
                    Search
                </a>
            </div>

            <ul class="hidden md:flex mt-6 flex-wrap gap-x-6 gap-y-2 text-sm text-gray-200">
                <li class="inline-flex items-center gap-2"><span class="text-orange-400">✓</span> 120+ verified properties</li>
                <li class="inline-flex items-center gap-2"><span class="text-orange-400">✓</span> Licensed broker</li>
                <li class="inline-flex items-center gap-2"><span class="text-orange-400">✓</span> No login needed</li>
            </ul>
        </div>
    </section>
</template>
