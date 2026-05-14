<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import Navbar from './components/Navbar.vue';
import Footer from './components/Footer.vue';
import BottomNav from './components/BottomNav.vue';

const initial = window.__INITIAL_ABOUT__;

const broker = {
    name: 'Marco V. Reyes',
    title: 'PRC-Licensed Real Estate Broker · PRB Lic. #0028451',
    chapter: 'PAREB-CDO Chapter Member',
    bio: [
        "Marco has spent the last twelve years brokering rentals across Cagayan de Oro, with experience spanning studio condos in Pueblo de Oro, family homes in Carmen, and properties throughout Lapasan. His practice centers on what tenants prioritize: fair pricing, responsive landlords, and clear terms at move-in.",
        "He partnered with RentConnectPH because the verification model aligns with how he prefers to broker — owners vetted up front, paperwork executed properly at closing, and a structured process throughout. Every lease that closes through the platform is drafted and witnessed by Marco personally, ensuring accountability on every contract.",
    ],
};

const howItWorks = [
    {
        step: '01',
        title: 'Search verified listings',
        body: 'Every listing on RentConnectPH is reviewed by our team before publishing. Photos, address, and pricing are verified, so what you see reflects what is actually available.',
    },
    {
        step: '02',
        title: 'Send an inquiry',
        body: 'Submit an inquiry on any listing with your move-in date and basic requirements. Our team contacts you to confirm details and arranges a viewing with the property owner.',
    },
    {
        step: '03',
        title: 'Sign with a licensed broker',
        body: "When you're ready to commit, our PRC-licensed broker handles the closing — drafting the lease, negotiating terms, and documenting deposits and fees clearly.",
    },
];

const trustStats = [
    { label: 'Cagayan de Oro–first',           detail: 'Focused on CDO neighborhoods including Pueblo de Oro, Carmen, Kauswagan, and Lapasan, with continued expansion.' },
    { label: 'Every listing verified',         detail: 'Each listing is reviewed by our team before publishing, with photos, address, and pricing verified.' },
    { label: 'Licensed broker on every lease', detail: 'Every closing is handled by our PRC-licensed broker, who drafts the lease and negotiates final terms.' },
    { label: 'Transparent pricing',            detail: 'All pricing in PHP. Monthly rent, deposits, and any platform fees disclosed before inquiry.' },
];

const showScrollTop = ref(false);

function onScroll() {
    showScrollTop.value = window.scrollY > 400;
}

function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
});
</script>

<template>
    <div class="min-h-screen flex flex-col bg-white dark:bg-gray-950 text-gray-900 dark:text-gray-100 pb-20 md:pb-0">
        <Navbar />

        <!-- Hero band -->
        <section class="relative isolate overflow-hidden text-white">
            <div class="absolute inset-0 bg-[linear-gradient(135deg,#ff8c42_0%,#e5722b_55%,#7a2d0a_100%)] dark:bg-[linear-gradient(135deg,#3a1d0e_0%,#2a1408_55%,#160901_100%)]"></div>
            <div class="absolute inset-0 mix-blend-multiply dark:opacity-30 bg-[radial-gradient(circle_at_80%_20%,rgba(255,200,140,0.35)_0%,rgba(255,140,66,0)_55%)]"></div>

            <div class="relative max-w-7xl mx-auto w-full px-4 md:px-6 lg:px-8 py-12 md:py-20">
                <div class="max-w-3xl">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/15 dark:bg-white/10 backdrop-blur text-xs font-semibold tracking-wide uppercase ring-1 ring-white/20">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-300"></span>
                        Cagayan de Oro
                    </div>
                    <h1 class="mt-5 text-3xl md:text-5xl lg:text-6xl font-bold tracking-tight leading-tight">
                        Rentals in CDO,<br class="hidden md:block" />
                        <span class="text-amber-200">without the guesswork.</span>
                    </h1>
                    <p class="mt-5 text-base md:text-lg text-orange-50/90 dark:text-orange-100/85 leading-relaxed">
                        RentConnectPH is a verified-listings platform for renters and property owners in Cagayan de Oro. We built it to give renters a single, reliable place to find a home — and to give owners a trusted channel to reach them.
                    </p>
                    <div class="mt-7 flex flex-wrap items-center gap-3">
                        <a
                            href="/search"
                            class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-white text-orange-700 dark:text-orange-800 font-semibold shadow-lg ring-1 ring-white/40 hover:bg-orange-50 active:bg-orange-100"
                        >
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="7" />
                                <path d="m20 20-3.5-3.5" />
                            </svg>
                            Browse listings
                        </a>
                        <a
                            href="#how-it-works"
                            class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-white/15 dark:bg-white/10 backdrop-blur text-white font-semibold ring-1 ring-white/30 hover:bg-white/25"
                        >
                            How it works
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Story -->
        <section class="max-w-7xl mx-auto w-full px-4 md:px-6 lg:px-8 py-12 md:py-20">
            <div class="grid md:grid-cols-3 gap-8 md:gap-12">
                <div class="md:col-span-1">
                    <div class="text-xs font-semibold uppercase tracking-wider text-orange-500">
                        Our story
                    </div>
                    <h2 class="mt-2 text-2xl md:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Built for renters<br />and CDO property owners.
                    </h2>
                </div>
                <div class="md:col-span-2 space-y-5 text-base md:text-lg text-gray-600 dark:text-gray-300 leading-relaxed">
                    <p>
                        Cagayan de Oro's rental market is fragmented across Facebook Marketplace, classified groups, and informal listings. Renters spend hours sorting through unreliable posts, often without a clear way to verify what is available or who they are dealing with.
                    </p>
                    <p>
                        RentConnectPH centralizes verified rental listings from CDO property owners into a single platform — searchable, filterable, with clear photos and PHP pricing. Owners reach renters through a trusted channel. Renters get a vetted shortlist, with a licensed broker handling the lease at closing.
                    </p>
                </div>
            </div>
        </section>

        <!-- How it works -->
        <section id="how-it-works" class="bg-gray-50 dark:bg-gray-900/40 border-y border-gray-100 dark:border-gray-800">
            <div class="max-w-7xl mx-auto w-full px-4 md:px-6 lg:px-8 py-12 md:py-20">
                <div class="max-w-2xl">
                    <div class="text-xs font-semibold uppercase tracking-wider text-orange-500">
                        How it works
                    </div>
                    <h2 class="mt-2 text-2xl md:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Three steps from search to signing.
                    </h2>
                </div>

                <div class="mt-10 grid md:grid-cols-3 gap-5 md:gap-6">
                    <div
                        v-for="item in howItWorks"
                        :key="item.step"
                        class="relative rounded-2xl bg-white dark:bg-gray-900 p-6 md:p-7 ring-1 ring-gray-100 dark:ring-gray-800 shadow-sm hover:shadow-md hover:ring-orange-200 dark:hover:ring-orange-900/60"
                    >
                        <div class="text-xs font-bold tracking-widest text-orange-500">
                            STEP {{ item.step }}
                        </div>
                        <h3 class="mt-3 text-lg md:text-xl font-bold text-gray-900 dark:text-white">
                            {{ item.title }}
                        </h3>
                        <p class="mt-2 text-sm md:text-base text-gray-600 dark:text-gray-300 leading-relaxed">
                            {{ item.body }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Meet the broker -->
        <section class="max-w-7xl mx-auto w-full px-4 md:px-6 lg:px-8 py-12 md:py-20">
            <div class="grid md:grid-cols-5 gap-8 md:gap-12 items-center">
                <div class="md:col-span-2">
                    <div class="relative max-w-sm mx-auto md:max-w-none">
                        <div class="absolute -inset-4 rounded-3xl bg-gradient-to-br from-orange-200/60 via-amber-100/40 to-transparent dark:from-orange-900/30 dark:via-amber-900/20 blur-2xl"></div>
                        <div class="relative rounded-3xl overflow-hidden ring-1 ring-gray-200 dark:ring-gray-800 shadow-xl bg-gray-100 dark:bg-gray-900 aspect-square">
                            <img
                                :src="initial.brokerImage"
                                :alt="broker.name"
                                class="w-full h-full object-cover"
                            />
                        </div>
                    </div>
                </div>
                <div class="md:col-span-3">
                    <div class="text-xs font-semibold uppercase tracking-wider text-orange-500">
                        Meet the broker
                    </div>
                    <h2 class="mt-2 text-2xl md:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                        {{ broker.name }}
                    </h2>
                    <div class="mt-2 text-sm md:text-base font-medium text-gray-600 dark:text-gray-400">
                        {{ broker.title }}
                    </div>
                    <div class="mt-1 inline-flex items-center gap-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/>
                        </svg>
                        {{ broker.chapter }}
                    </div>
                    <div class="mt-5 space-y-4 text-base md:text-lg text-gray-600 dark:text-gray-300 leading-relaxed">
                        <p v-for="(para, i) in broker.bio" :key="i">{{ para }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Trust strip -->
        <section class="bg-gray-50 dark:bg-gray-900/40 border-y border-gray-100 dark:border-gray-800">
            <div class="max-w-7xl mx-auto w-full px-4 md:px-6 lg:px-8 py-12 md:py-16">
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5 md:gap-6">
                    <div
                        v-for="stat in trustStats"
                        :key="stat.label"
                        class="rounded-2xl bg-white dark:bg-gray-900 p-5 md:p-6 ring-1 ring-gray-100 dark:ring-gray-800"
                    >
                        <div class="flex items-center gap-2">
                            <span class="w-8 h-8 rounded-lg bg-orange-50 dark:bg-orange-500/10 text-orange-500 flex items-center justify-center">
                                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/>
                                </svg>
                            </span>
                            <div class="text-sm md:text-base font-semibold text-gray-900 dark:text-white">
                                {{ stat.label }}
                            </div>
                        </div>
                        <p class="mt-3 text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            {{ stat.detail }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA band -->
        <section class="relative isolate overflow-hidden text-white">
            <div class="absolute inset-0 bg-[linear-gradient(135deg,#ff8c42_0%,#e5722b_55%,#7a2d0a_100%)] dark:bg-[linear-gradient(135deg,#3a1d0e_0%,#2a1408_55%,#160901_100%)]"></div>
            <div class="absolute inset-0 mix-blend-multiply dark:opacity-30 bg-[radial-gradient(circle_at_20%_80%,rgba(255,200,140,0.35)_0%,rgba(255,140,66,0)_55%)]"></div>

            <div class="relative max-w-7xl mx-auto w-full px-4 md:px-6 lg:px-8 py-12 md:py-16">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                    <div class="max-w-xl">
                        <h2 class="text-2xl md:text-3xl font-bold tracking-tight">
                            Looking for a place in CDO?
                        </h2>
                        <p class="mt-3 text-orange-50/90 dark:text-orange-100/85">
                            Start with our verified listings. Every one is reviewed before it goes live.
                        </p>
                    </div>
                    <a
                        href="/search"
                        class="inline-flex items-center gap-2 px-6 py-3.5 rounded-xl bg-white text-orange-700 dark:text-orange-800 font-semibold shadow-lg ring-1 ring-white/40 hover:bg-orange-50 active:bg-orange-100 self-start md:self-auto"
                    >
                        Browse verified listings
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14M13 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
            </div>
        </section>

        <!-- Closing strip -->
        <section class="border-t border-gray-100 dark:border-gray-800">
            <div class="max-w-7xl mx-auto w-full px-4 md:px-6 lg:px-8 py-6 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-xs md:text-sm text-gray-500 dark:text-gray-400">
                <a href="/privacy" class="hover:text-orange-600 dark:hover:text-orange-400">Privacy Policy</a>
                <span aria-hidden="true" class="text-gray-300 dark:text-gray-700">·</span>
                <a href="/terms" class="hover:text-orange-600 dark:hover:text-orange-400">Terms of Service</a>
                <span aria-hidden="true" class="text-gray-300 dark:text-gray-700">·</span>
                <p>© 2026 RentConnectPH · Cagayan de Oro</p>
            </div>
        </section>

        <Footer />
        <BottomNav />

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-2"
        >
            <button
                v-if="showScrollTop"
                @click="scrollToTop"
                aria-label="Scroll to top"
                class="md:hidden fixed bottom-24 right-4 z-40 w-12 h-12 rounded-full bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white shadow-xl ring-2 ring-white dark:ring-gray-900 flex items-center justify-center"
            >
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m18 15-6-6-6 6"/>
                </svg>
            </button>
        </Transition>
    </div>
</template>
