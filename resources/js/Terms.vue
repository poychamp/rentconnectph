<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import Navbar from './components/Navbar.vue';
import Footer from './components/Footer.vue';
import BottomNav from './components/BottomNav.vue';

const initial = window.__INITIAL_TERMS__;

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

            <div class="relative max-w-4xl mx-auto w-full px-4 md:px-6 lg:px-8 py-10 md:py-14">
                <div class="text-xs font-semibold uppercase tracking-wider text-orange-100/90">
                    Legal
                </div>
                <h1 class="mt-3 text-3xl md:text-5xl font-bold tracking-tight leading-tight">
                    Terms of Service
                </h1>
                <p class="mt-4 text-sm md:text-base text-orange-50/90">
                    Effective date: {{ initial.effectiveDate }}
                </p>
            </div>
        </section>

        <!-- Body -->
        <main class="flex-1">
            <article class="max-w-4xl mx-auto w-full px-4 md:px-6 lg:px-8 py-10 md:py-16 space-y-10 md:space-y-14 text-gray-700 dark:text-gray-300 leading-relaxed">
                <!-- Intro -->
                <section class="space-y-4">
                    <p>
                        These Terms of Service ("Terms") govern your access to and use of RentConnectPH ("RentConnectPH", "we", "us"), operated by Leoncio Cesar Polestico, including our website, mobile applications, and related services (collectively, the "Platform").
                    </p>
                    <p>
                        By accessing or using the Platform, you agree to be bound by these Terms. If you do not agree, please do not use the Platform.
                    </p>
                </section>

                <!-- 1. About -->
                <section class="space-y-4">
                    <h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">
                        1. About RentConnectPH
                    </h2>
                    <p>
                        RentConnectPH is an online rental-listing platform serving Cagayan de Oro, Philippines. The Platform allows prospective renters to browse verified rental listings submitted by property owners, send inquiries, and coordinate viewings. Lease closings on the Platform are handled by Marco V. Reyes, PRC-licensed real estate broker (PRB Lic. #0028451, PAREB-CDO Chapter), who drafts and witnesses every lease executed through the Platform.
                    </p>
                    <p>
                        RentConnectPH is not a real estate broker, agent, or party to any lease agreement. The Platform connects renters with property owners and provides supporting services around inquiry handling and lease closing.
                    </p>
                </section>

                <!-- 2. Eligibility -->
                <section class="space-y-4">
                    <h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">
                        2. Eligibility
                    </h2>
                    <p>
                        You must be at least 18 years of age and legally capable of entering into binding contracts under Philippine law to use the Platform. By using the Platform, you represent and warrant that you meet these requirements.
                    </p>
                </section>

                <!-- 3. Accounts -->
                <section class="space-y-4">
                    <h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">
                        3. Accounts
                    </h2>
                    <p>
                        Some features of the Platform require an account (for example, administrators, calls team members, field officers, and the closing broker). You are responsible for:
                    </p>
                    <ul class="list-disc pl-6 space-y-1.5">
                        <li>Providing accurate and current information when registering.</li>
                        <li>Keeping your credentials confidential.</li>
                        <li>All activity that occurs under your account.</li>
                    </ul>
                    <p>
                        You must notify us immediately of any unauthorized access. We may suspend or terminate accounts that violate these Terms or that we reasonably believe pose a risk to other users or to the Platform.
                    </p>
                </section>

                <!-- 4. Listings -->
                <section class="space-y-4">
                    <h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">
                        4. Listings
                    </h2>
                    <p>
                        Listings on the Platform are submitted by property owners and reviewed by our team before being published. We make reasonable efforts to verify the information provided, but:
                    </p>
                    <ul class="list-disc pl-6 space-y-1.5">
                        <li>We do not guarantee the accuracy, completeness, availability, or legality of any listing.</li>
                        <li>Photographs, pricing, amenities, and other listing details are provided by the property owner and may change without notice.</li>
                        <li>We are not responsible for the condition of any property or for representations made directly between renters and property owners outside the Platform.</li>
                    </ul>
                    <p>
                        If you are a property owner submitting a listing, you represent that you have the legal right to offer the property for rent, that all information you provide is accurate, and that the photographs you upload are yours or licensed for your use.
                    </p>
                </section>

                <!-- 5. Inquiries -->
                <section class="space-y-4">
                    <h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">
                        5. Inquiries and viewings
                    </h2>
                    <p>
                        When you submit an inquiry on a listing, our calls team may contact you to qualify the inquiry and coordinate a viewing with the property owner. The exact address of the property and the property owner's direct contact information are disclosed only after a viewing has been scheduled, in accordance with our renter-privacy practices.
                    </p>
                    <p>
                        We do not guarantee a response from any property owner, the availability of any specific property, or the success of any viewing.
                    </p>
                </section>

                <!-- 6. Closings -->
                <section class="space-y-4">
                    <h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">
                        6. Lease closings
                    </h2>
                    <p>
                        Lease agreements arranged through the Platform are drafted, witnessed, and executed by Marco V. Reyes, our PRC-licensed closing broker. Renters and property owners remain the contracting parties to the lease. RentConnectPH is not a party to the lease and is not responsible for the parties' performance under it. Disputes arising under a lease should be resolved between the parties, with the assistance of the closing broker where appropriate.
                    </p>
                </section>

                <!-- 7. Acceptable use -->
                <section class="space-y-4">
                    <h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">
                        7. Acceptable use
                    </h2>
                    <p>
                        When using the Platform, you agree not to:
                    </p>
                    <ul class="list-disc pl-6 space-y-1.5">
                        <li>Submit false, misleading, fraudulent, or duplicate listings.</li>
                        <li>Impersonate any person or misrepresent your identity, credentials, or affiliation.</li>
                        <li>Use automated tools to scrape, crawl, harvest, or otherwise extract data from the Platform without our express written permission.</li>
                        <li>Interfere with or disrupt the Platform, its servers, or its underlying infrastructure.</li>
                        <li>Attempt to gain unauthorized access to any account, system, or data.</li>
                        <li>Use the Platform to send spam, unsolicited communications, or unlawful content.</li>
                        <li>Use the Platform in any way that violates Philippine law or the rights of any third party.</li>
                    </ul>
                    <p>
                        We reserve the right to remove content, suspend access, or terminate accounts that we reasonably believe violate these rules.
                    </p>
                </section>

                <!-- 8. IP -->
                <section class="space-y-4">
                    <h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">
                        8. Intellectual property
                    </h2>
                    <p>
                        All content on the Platform that is not user-submitted, including the RentConnectPH name, logo, design, source code, copy, and curated materials, is owned by Leoncio Cesar Polestico or our licensors and is protected by Philippine and international intellectual property laws.
                    </p>
                    <p>
                        Property owners who submit listings grant RentConnectPH a non-exclusive, royalty-free license to host, display, reproduce, and distribute the submitted content (including photographs and descriptions) on the Platform and in marketing materials for the purpose of operating and promoting the Platform.
                    </p>
                </section>

                <!-- 9. Third-party -->
                <section class="space-y-4">
                    <h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">
                        9. Third-party services
                    </h2>
                    <p>
                        The Platform integrates with third-party services (for example, Amazon Web Services, Algolia, Google Analytics, mapping providers, and email-delivery services) to function. Your use of those services may be subject to their own terms and privacy policies. We are not responsible for the practices of third-party services beyond our reasonable control.
                    </p>
                </section>

                <!-- 10. Disclaimers -->
                <section class="space-y-4">
                    <h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">
                        10. Disclaimers
                    </h2>
                    <p>
                        The Platform is provided on an "as is" and "as available" basis, without warranties of any kind, whether express, implied, or statutory, to the maximum extent permitted by Philippine law. We do not warrant that the Platform will be uninterrupted, error-free, or secure, nor that any listing, inquiry, or transaction will result in a successful tenancy.
                    </p>
                    <p>
                        You acknowledge that the Platform is an information service and that any decision to view, inquire about, or lease a property is your own.
                    </p>
                </section>

                <!-- 11. Liability -->
                <section class="space-y-4">
                    <h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">
                        11. Limitation of liability
                    </h2>
                    <p>
                        To the maximum extent permitted by Philippine law, RentConnectPH and Leoncio Cesar Polestico shall not be liable for any indirect, incidental, special, consequential, or exemplary damages, or for any loss of profits, revenue, data, or use, arising out of or in connection with your use of the Platform, even if we have been advised of the possibility of such damages.
                    </p>
                    <p>
                        Our total aggregate liability for any claim arising out of or relating to these Terms or the Platform shall not exceed PHP 1,000.00 or the amount you have paid to us in the twelve (12) months preceding the claim, whichever is greater.
                    </p>
                </section>

                <!-- 12. Indemnification -->
                <section class="space-y-4">
                    <h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">
                        12. Indemnification
                    </h2>
                    <p>
                        You agree to indemnify, defend, and hold harmless RentConnectPH and Leoncio Cesar Polestico from and against any claims, damages, liabilities, losses, costs, and expenses (including reasonable attorney's fees) arising out of or related to:
                    </p>
                    <ul class="list-disc pl-6 space-y-1.5">
                        <li>Your use of the Platform.</li>
                        <li>Your violation of these Terms.</li>
                        <li>Your violation of any law or the rights of any third party.</li>
                        <li>Any content you submit to the Platform.</li>
                    </ul>
                </section>

                <!-- 13. Termination -->
                <section class="space-y-4">
                    <h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">
                        13. Termination
                    </h2>
                    <p>
                        We may suspend or terminate your access to the Platform at any time, with or without notice, for any reason, including violation of these Terms. You may stop using the Platform at any time. Sections of these Terms that by their nature should survive termination (including intellectual property, disclaimers, limitation of liability, indemnification, and governing law) will survive.
                    </p>
                </section>

                <!-- 14. Changes -->
                <section class="space-y-4">
                    <h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">
                        14. Changes to these Terms
                    </h2>
                    <p>
                        We may update these Terms from time to time. The "Effective date" at the top of these Terms indicates when they were last revised. Continued use of the Platform after changes are posted constitutes your acceptance of the updated Terms. If you do not agree to the updated Terms, please stop using the Platform.
                    </p>
                </section>

                <!-- 15. Governing law -->
                <section class="space-y-4">
                    <h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">
                        15. Governing law and venue
                    </h2>
                    <p>
                        These Terms are governed by and construed in accordance with the laws of the Republic of the Philippines, without regard to its conflict-of-laws principles. You agree that any dispute arising out of or relating to these Terms or the Platform shall be subject to the exclusive jurisdiction of the courts of Cagayan de Oro, Philippines.
                    </p>
                </section>

                <!-- 16. Contact -->
                <section class="space-y-4">
                    <h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">
                        16. Contact us
                    </h2>
                    <p>
                        For questions about these Terms, please reach us through
                        <a href="/contact" class="text-orange-600 dark:text-orange-400 hover:underline">our contact form</a>.
                    </p>
                </section>
            </article>
        </main>

        <Footer />
        <BottomNav />

        <!-- Scroll-to-top FAB -->
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
                class="md:hidden fixed bottom-24 right-4 w-12 h-12 rounded-full bg-orange-500 hover:bg-orange-600 text-white shadow-lg flex items-center justify-center z-40"
            >
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 15l-6-6-6 6" />
                </svg>
            </button>
        </Transition>
    </div>
</template>
